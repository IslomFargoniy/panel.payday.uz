<?php

namespace App\Http\Controllers;

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDay;
use App\Models\Branch\BranchHoliday;
use App\Models\Firm\Firm;
use App\Models\Firm\FirmHoliday;
use App\Models\Worker\Worker;
use App\Models\Worker\WorkerDay;
use App\Models\Worker\WorkerHoliday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReportController extends Controller
{
    /**
     * Build the paired events query using window functions (LEAD, ROW_NUMBER).
     * Replaces expensive derived-table self-joins and correlated subqueries.
     */
    private function buildPairedEventsQuery(Request $request, string $from, string $to)
    {
        $eventsWithLead = DB::table('hikvision_access_events as hae')
            ->select(
                'hae.id',
                'hae.employeeNoString',
                'w.id as worker_id',
                'w.name as worker',
                'w.phone',
                'b.name as branch',
                'hae.work_time',
                'hae.end_time',
                'f.name as firm',
                'hae.attendanceStatus as status_from',
                'hae.label as label_from',
                'hae.created_at as from_time',
                DB::raw("LEAD(hae.created_at) OVER (PARTITION BY hae.employeeNoString ORDER BY hae.created_at) AS to_time"),
                DB::raw("LEAD(hae.attendanceStatus) OVER (PARTITION BY hae.employeeNoString ORDER BY hae.created_at) AS status_to"),
                DB::raw("LEAD(hae.label) OVER (PARTITION BY hae.employeeNoString ORDER BY hae.created_at) AS label_to"),
                DB::raw("ROW_NUMBER() OVER (PARTITION BY hae.employeeNoString, CAST(hae.created_at AS DATE) ORDER BY hae.created_at) AS day_rn")
            )
            ->join('workers as w', 'w.employeeNoString', '=', 'hae.employeeNoString')
            ->join('branches as b', 'w.branch_id', '=', 'b.id')
            ->join('firms as f', 'b.firm_id', '=', 'f.id')
            ->whereNull('hae.deleted_at')
            ->whereBetween('hae.created_at', [$from, $to . " 23:59:59"]);

        if ($request->worker_id) {
            $eventsWithLead->where('w.id', $request->worker_id);
        }
        if ($request->search) {
            $eventsWithLead->where(function ($query) use ($request) {
                $query->where('w.name', 'like', '%' . $request->search . '%')
                    ->orWhere('b.name', 'like', '%' . $request->search . '%')
                    ->orWhere('f.name', 'like', '%' . $request->search . '%')
                    ->orWhere('hae.label', 'like', '%' . $request->search . '%')
                    ->orWhere('w.phone', 'like', '%' . $request->search . '%')
                    ->orWhere('w.address', 'like', '%' . $request->search . '%')
                    ->orWhere('w.comment', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->branch_id) {
            $eventsWithLead->where('b.id', $request->branch_id);
        }
        if ($request->firm_id) {
            $eventsWithLead->where('f.id', $request->firm_id);
        }

        if (!Auth::user()->hasRole('Admin')) {
            $firmIds = Auth::user()->user_firms()->pluck('firm_id');
            $eventsWithLead->whereIn('f.id', $firmIds);
        }

        $pairedQuery = DB::table(DB::raw("({$eventsWithLead->toSql()}) as pe"))
            ->mergeBindings($eventsWithLead)
            ->where(function ($q) {
                $q->whereIn('pe.status_from', ['keldi', 'CheckIn', 'checkIn', 'entered'])
                  ->whereIn('pe.status_to', ['ketdi', 'CheckOut', 'checkOut', 'exited']);
            })
            ->select(
                'pe.id',
                'pe.employeeNoString',
                'pe.worker_id',
                'pe.worker',
                'pe.phone',
                'pe.branch',
                'pe.work_time',
                'pe.end_time',
                'pe.firm',
                'pe.from_time',
                'pe.to_time',
                'pe.status_from',
                DB::raw("CONCAT(pe.label_from, '/', pe.label_to) as status"),
                DB::raw("CASE
                    WHEN pe.status_from IN ('keldi', 'CheckIn', 'checkIn', 'entered') AND pe.day_rn = 1
                    THEN TIMESTAMPDIFF(MINUTE, TIMESTAMP(DATE(pe.from_time), pe.work_time), pe.from_time)
                    ELSE 0
                END as late_minutes"),
                DB::raw("CASE
                    WHEN pe.status_from IN ('keldi', 'CheckIn', 'checkIn', 'entered')
                    THEN TIMESTAMPDIFF(MINUTE, pe.from_time, pe.to_time)
                    ELSE 0
                END as worked_minutes"),
                DB::raw("0 as break_minutes")
            );

        return $pairedQuery;
    }

    public function salary_report(Request $request)
    {
        try {
            $request->validate([
                'from' => 'nullable',
                'to' => 'nullable',
                'worker_id' => 'nullable',
                'branch_id' => 'nullable',
                'firm_id' => 'nullable',
            ]);

            $per_page = $request->per_page ? (int)$request->per_page : 15;
            $from = $request->from ?: Carbon::now()->startOfMonth()->toDateString();
            $to = $request->to ?: Carbon::now()->toDateString();
            $request->merge(['from' => $from, 'to' => $to]);

            $days = $this->countWorkingDays($request);

            $pairedEvents = $this->buildPairedEventsQuery($request, $from, $to);

            // 1. Paginated records
            $results = DB::table(DB::raw("({$pairedEvents->toSql()}) as paired_events"))
                ->mergeBindings($pairedEvents)
                ->select(
                    'id',
                    'worker_id',
                    'worker',
                    'phone',
                    'branch',
                    'work_time',
                    'end_time',
                    'firm',
                    DB::raw('from_time as `from`'),
                    DB::raw('to_time as `to`'),
                    'worked_minutes',
                    'break_minutes',
                    DB::raw('IF(late_minutes > 0, late_minutes, 0) as late_minutes'),
                    'status'
                )
                ->orderBy('worker')
                ->orderBy('from_time', 'desc')
                ->paginate($per_page);

            // 2. Summary totals calculated directly in SQL (zero heavy in-memory hydration)
            $summary = DB::table(DB::raw("({$pairedEvents->toSql()}) as paired_events"))
                ->mergeBindings($pairedEvents)
                ->selectRaw("
                    COALESCE(SUM(worked_minutes), 0) as worked_minutes,
                    COALESCE(SUM(break_minutes), 0) as break_minutes,
                    COALESCE(SUM(IF(late_minutes > 0, late_minutes, 0)), 0) as late_minutes,
                    COALESCE(COUNT(DISTINCT CONCAT(worker_id, '_', DATE(from_time))), 0) as worked_days,
                    COALESCE(COUNT(DISTINCT CASE WHEN late_minutes > 0 THEN CONCAT(worker_id, '_', DATE(from_time)) END), 0) as late_days
                ")
                ->first();

            $report = new \stdClass();
            $report->working_days = $days;
            $report->worked_days = (int) ($summary->worked_days ?? 0);
            $report->worked_minutes = (int) ($summary->worked_minutes ?? 0);
            $report->break_minutes = (int) ($summary->break_minutes ?? 0);
            $report->late_minutes = (int) ($summary->late_minutes ?? 0);
            $report->late_days = (int) ($summary->late_days ?? 0);

            if ($request->worker_id) {
                $worker = Worker::without(['branch'])->find($request->worker_id);
                if ($worker) {
                    $report->last_salary_date = $worker->salaries()->max('to');
                    $report->hour_price = $worker->hour_price;
                    $report->fine_price = $worker->fine_price;
                    $report->work_time = $worker->work_time;
                    $report->end_time = $worker->end_time;
                }
            }
            $report->from = $request->from;
            $report->to = $request->to;

            // 3. Optimized dropdown lists (lean columns, without unnecessary relations)
            $firms = Firm::query()->without(['branches'])->select('id', 'name');
            $branches = Branch::query()->without(['firm', 'workers'])->select('id', 'firm_id', 'name');
            $workers = Worker::query()->without(['branch'])->select('id', 'branch_id', 'name');

            if (!Auth::user()->hasRole('Admin')) {
                $userFirms = Auth::user()->user_firms()->pluck('firm_id');
                $firms->whereIn('id', $userFirms);
                $branches->whereIn('firm_id', $userFirms);
                $workers->whereHas('branch', function ($q) use ($userFirms) {
                    $q->whereIn('firm_id', $userFirms);
                });
            }

            return Inertia::render('salary_report/index', [
                'report' => $report,
                'attendance' => $results,
                'firms' => $firms->get(),
                'branches' => $branches->get(),
                'workers' => $workers->get(),
            ]);

        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()],
            ]);
        }
    }

    public function countWorkingDays(Request $request): int
    {
        $workerId = $request->worker_id > 0 ? (int)$request->worker_id : null;
        $branchId = $request->branch_id > 0 ? (int)$request->branch_id : null;
        $firmId = $request->firm_id > 0 ? (int)$request->firm_id : null;
        $from = Carbon::parse($request->from)->startOfDay();
        $to = Carbon::parse($request->to)->startOfDay();

        $worker = $workerId ? Worker::without(['branch'])->find($workerId) : null;
        $effectiveBranchId = $branchId ?? $worker?->branch_id;
        $effectiveFirmId = $firmId;
        if (!$effectiveFirmId && $effectiveBranchId) {
            $effectiveFirmId = Branch::without(['firm'])->where('id', $effectiveBranchId)->value('firm_id');
        }

        // 1. Fetch working day indexes (prioritize worker_days then branch_days)
        $workingDayIndexes = range(1, 7);
        if ($workerId) {
            $workerDays = WorkerDay::where('worker_id', $workerId)->with('day')->get();
            if ($workerDays->isNotEmpty()) {
                $workingDayIndexes = $workerDays->pluck('day.index')->filter()->toArray();
            } elseif ($effectiveBranchId) {
                $branchDays = BranchDay::where('branch_id', $effectiveBranchId)->with('day')->get();
                if ($branchDays->isNotEmpty()) {
                    $workingDayIndexes = $branchDays->pluck('day.index')->filter()->toArray();
                }
            }
        } elseif ($effectiveBranchId) {
            $branchDays = BranchDay::where('branch_id', $effectiveBranchId)->with('day')->get();
            if ($branchDays->isNotEmpty()) {
                $workingDayIndexes = $branchDays->pluck('day.index')->filter()->toArray();
            }
        }

        // 2. Pre-fetch holidays once in batch (3 fast queries max)
        $workerHolidays = [];
        if ($workerId) {
            $workerHolidays = WorkerHoliday::where('worker_id', $workerId)
                ->where('from', '<=', $to->toDateString())
                ->where('to', '>=', $from->toDateString())
                ->get(['from', 'to']);
        }

        $branchHolidayDates = [];
        if ($effectiveBranchId) {
            $branchHolidayDates = BranchHoliday::where('branch_id', $effectiveBranchId)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->pluck('date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->flip()
                ->toArray();
        }

        $firmHolidayDates = [];
        if ($effectiveFirmId) {
            $firmHolidayDates = FirmHoliday::where('firm_id', $effectiveFirmId)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->pluck('date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->flip()
                ->toArray();
        }

        // 3. Count in pure memory
        $count = 0;
        foreach (CarbonPeriod::create($from, $to) as $date) {
            $dayOfWeek = $date->dayOfWeekIso;
            if (!in_array($dayOfWeek, $workingDayIndexes)) {
                continue;
            }

            $dateStr = $date->toDateString();

            if (isset($branchHolidayDates[$dateStr]) || isset($firmHolidayDates[$dateStr])) {
                continue;
            }

            $isWorkerHoliday = false;
            foreach ($workerHolidays as $wh) {
                if ($dateStr >= $wh->from && $dateStr <= $wh->to) {
                    $isWorkerHoliday = true;
                    break;
                }
            }
            if ($isWorkerHoliday) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    public function monthly_attendance(Request $request)
    {
        $per_page = $request->per_page ? (int)$request->per_page : 15;

        if ($request->from && $request->to) {
            $from = $request->from;
            $to = $request->to;
        } else {
            $from = Carbon::now()->startOfMonth()->toDateString();
            $to = Carbon::now()->toDateString();
        }

        $pairedEvents = $this->buildPairedEventsQuery($request, $from, $to);

        // Pre-aggregate paired events by worker in a subquery
        $summarySubquery = DB::table(DB::raw("({$pairedEvents->toSql()}) as pe"))
            ->mergeBindings($pairedEvents)
            ->select(
                'pe.worker_id',
                DB::raw('COALESCE(SUM(pe.worked_minutes), 0) AS worked_minutes'),
                DB::raw('COALESCE(SUM(pe.break_minutes), 0) AS break_minutes'),
                DB::raw('COALESCE(SUM(IF(pe.late_minutes > 0, pe.late_minutes, 0)), 0) AS late_minutes'),
                DB::raw('COALESCE(COUNT(DISTINCT DATE(pe.from_time)), 0) AS worked_days'),
                DB::raw('COALESCE(COUNT(DISTINCT CASE WHEN pe.late_minutes > 0 THEN DATE(pe.from_time) END), 0) AS late_days')
            )
            ->groupBy('pe.worker_id');

        $resultsForReport = Worker::query()
            ->with(['branch.firm'])
            ->leftJoin(DB::raw("({$summarySubquery->toSql()}) as s"), 'workers.id', '=', 's.worker_id')
            ->mergeBindings($summarySubquery)
            ->select(
                'workers.*',
                DB::raw('COALESCE(s.worked_minutes, 0) AS worked_minutes'),
                DB::raw('COALESCE(s.break_minutes, 0) AS break_minutes'),
                DB::raw('COALESCE(s.late_minutes, 0) AS late_minutes'),
                DB::raw('COALESCE(s.worked_days, 0) AS worked_days'),
                DB::raw('COALESCE(s.late_days, 0) AS late_days')
            )
            ->orderByDesc('worked_minutes');

        if ($request->search) {
            $resultsForReport->where(function ($query) use ($request) {
                $query->where('workers.name', 'like', "%{$request->search}%")
                    ->orWhere('workers.phone', 'like', "%{$request->search}%")
                    ->orWhere('workers.address', 'like', "%{$request->search}%")
                    ->orWhere('workers.comment', 'like', "%{$request->search}%");
            });
        }

        if ($request->branch_id) {
            $resultsForReport->where('workers.branch_id', $request->branch_id);
        }

        if ($request->firm_id) {
            $resultsForReport->whereHas('branch', function ($query) use ($request) {
                $query->where('firm_id', '=', $request->firm_id);
            });
        }

        if (!Auth::user()->hasRole('Admin')) {
            $firmIds = Auth::user()->user_firms()->pluck('firm_id');
            $resultsForReport->whereHas('branch', function ($query) use ($firmIds) {
                $query->whereIn('firm_id', $firmIds);
            });
        }

        // 1. Paginate workers
        $paginatedWorkers = $resultsForReport->paginate($per_page);

        // 2. Batch calculate work_days only for the paginated workers on the current page
        $workingDaysMap = $this->batchCalculateWorkingDays($paginatedWorkers->items(), $from, $to);

        foreach ($paginatedWorkers as $workerItem) {
            $workerItem->work_days = $workingDaysMap[$workerItem->id] ?? 0;
        }

        // 3. Dropdown filters (lean payload)
        $firms = Firm::query()->without(['branches'])->select('id', 'name');
        $branches = Branch::query()->without(['firm', 'workers'])->select('id', 'firm_id', 'name');

        if (!Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $firms->whereIn('id', $userFirms);
            $branches->whereIn('firm_id', $userFirms);
        }

        return Inertia::render('monthly_report/index', [
            'worker' => $paginatedWorkers,
            'firms' => $firms->get(),
            'branches' => $branches->get(),
        ]);
    }

    private function batchCalculateWorkingDays(array $workers, string $fromStr, string $toStr): array
    {
        if (empty($workers)) {
            return [];
        }

        $from = Carbon::parse($fromStr)->startOfDay();
        $to = Carbon::parse($toStr)->startOfDay();
        $workerIds = array_map(fn($w) => $w->id, $workers);
        $branchIds = array_values(array_filter(array_unique(array_map(fn($w) => $w->branch_id, $workers))));
        $firmIds = array_values(array_filter(array_unique(array_map(fn($w) => $w->branch?->firm_id, $workers))));

        // 1. Worker days & Branch days
        $workerDaysMap = WorkerDay::whereIn('worker_id', $workerIds)
            ->with('day')
            ->get()
            ->groupBy('worker_id')
            ->map(fn($days) => $days->pluck('day.index')->filter()->toArray());

        $branchDaysMap = BranchDay::whereIn('branch_id', $branchIds)
            ->with('day')
            ->get()
            ->groupBy('branch_id')
            ->map(fn($days) => $days->pluck('day.index')->filter()->toArray());

        // 2. Holidays
        $workerHolidaysMap = WorkerHoliday::whereIn('worker_id', $workerIds)
            ->where('from', '<=', $to->toDateString())
            ->where('to', '>=', $from->toDateString())
            ->get(['worker_id', 'from', 'to'])
            ->groupBy('worker_id');

        $branchHolidaysMap = BranchHoliday::whereIn('branch_id', $branchIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['branch_id', 'date'])
            ->groupBy('branch_id')
            ->map(fn($items) => $items->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString())->flip()->toArray());

        $firmHolidaysMap = FirmHoliday::whereIn('firm_id', $firmIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['firm_id', 'date'])
            ->groupBy('firm_id')
            ->map(fn($items) => $items->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString())->flip()->toArray());

        // 3. Calculate for each worker
        $result = [];
        $dates = iterator_to_array(CarbonPeriod::create($from, $to));

        foreach ($workers as $worker) {
            $workingDayIndexes = $workerDaysMap->get($worker->id)
                ?? $branchDaysMap->get($worker->branch_id)
                ?? range(1, 7);

            $wHolidays = $workerHolidaysMap->get($worker->id) ?? collect();
            $bHolidays = $branchHolidaysMap->get($worker->branch_id) ?? [];
            $fHolidays = $firmHolidaysMap->get($worker->branch?->firm_id) ?? [];

            $count = 0;
            foreach ($dates as $date) {
                if (!in_array($date->dayOfWeekIso, $workingDayIndexes)) {
                    continue;
                }

                $dateStr = $date->toDateString();
                if (isset($bHolidays[$dateStr]) || isset($fHolidays[$dateStr])) {
                    continue;
                }

                $isWHoliday = false;
                foreach ($wHolidays as $wh) {
                    if ($dateStr >= $wh->from && $dateStr <= $wh->to) {
                        $isWHoliday = true;
                        break;
                    }
                }
                if ($isWHoliday) {
                    continue;
                }

                $count++;
            }
            $result[$worker->id] = $count;
        }

        return $result;
    }
}
