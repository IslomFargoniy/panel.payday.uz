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
    public function buildPairedEventsQuery(Request $request, string $from, string $to)
    {
        return app(\App\Services\Attendance\AttendancePairingService::class)->buildPairedEventsQuery($request, $from, $to);
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
        $from = Carbon::parse($request->from)->startOfDay();
        $to = Carbon::parse($request->to)->startOfDay();

        $scheduleService = new \App\Services\Attendance\WorkScheduleService();

        if ($workerId) {
            $worker = Worker::with(['branch'])->find($workerId);
            if ($worker) {
                return $scheduleService->countWorkingDays($worker, $from, $to);
            }
        }

        return $scheduleService->countWorkingDaysForBranch($branchId, $from, $to);
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
                DB::raw('COALESCE(SUM(pe.late_minutes), 0) AS late_minutes'),
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
        return (new \App\Services\Attendance\WorkScheduleService())->batchCalculateWorkingDays($workers, $fromStr, $toStr);
    }
}
