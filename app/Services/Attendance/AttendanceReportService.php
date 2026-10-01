<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Branch\Branch;
use App\Models\Firm\Firm;
use App\Models\Worker\Worker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceReportService
{
    protected AttendancePairingService $pairingService;
    protected WorkScheduleService $scheduleService;

    public function __construct(
        AttendancePairingService $pairingService,
        WorkScheduleService $scheduleService
    ) {
        $this->pairingService = $pairingService;
        $this->scheduleService = $scheduleService;
    }

    /**
     * Get data for Salary Report (Web and Mobile API)
     */
    public function getSalaryReportData(Request $request): array
    {
        $per_page = $request->per_page ? (int)$request->per_page : 15;
        $from = $request->from ?: Carbon::now()->startOfMonth()->toDateString();
        $to = $request->to ?: Carbon::now()->toDateString();
        $request->merge(['from' => $from, 'to' => $to]);

        $days = $this->countWorkingDays($request);

        $pairedEvents = $this->pairingService->buildPairedEventsQuery($request, $from, $to);

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

        // 2. Summary totals calculated directly in SQL
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

        // 3. Dropdown lists
        $firms = Firm::query()->without(['branches'])->select('id', 'name');
        $branches = Branch::query()->without(['firm', 'workers'])->select('id', 'firm_id', 'name');
        $workers = Worker::query()->without(['branch'])->select('id', 'branch_id', 'name');

        if (Auth::check() && !Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $firms->whereIn('id', $userFirms);
            $branches->whereIn('firm_id', $userFirms);
            $workers->whereHas('branch', function ($q) use ($userFirms) {
                $q->whereIn('firm_id', $userFirms);
            });
        }

        return [
            'report' => $report,
            'attendance' => $results,
            'firms' => $firms->get(),
            'branches' => $branches->get(),
            'workers' => $workers->get(),
        ];
    }

    /**
     * Get data for Monthly Attendance Report (Web and Mobile API)
     */
    public function getMonthlyAttendanceData(Request $request): array
    {
        $per_page = $request->per_page ? (int)$request->per_page : 15;
        $from = $request->from ?: Carbon::now()->startOfMonth()->toDateString();
        $to = $request->to ?: Carbon::now()->toDateString();
        $request->merge(['from' => $from, 'to' => $to]);

        $pairedEvents = $this->pairingService->buildPairedEventsQuery($request, $from, $to);

        $summarySubquery = DB::table(DB::raw("({$pairedEvents->toSql()}) as pe"))
            ->mergeBindings($pairedEvents)
            ->select(
                'pe.worker_id',
                DB::raw('COALESCE(SUM(pe.worked_minutes), 0) AS worked_minutes'),
                DB::raw('COALESCE(SUM(pe.break_minutes), 0) AS break_minutes'),
                DB::raw('COALESCE(SUM(IF(pe.late_minutes > 0, pe.late_minutes, 0)), 0) AS late_minutes'),
                DB::raw('COALESCE(COUNT(DISTINCT CONCAT(pe.worker_id, "_", DATE(pe.from_time))), 0) AS worked_days'),
                DB::raw('COALESCE(COUNT(DISTINCT CASE WHEN pe.late_minutes > 0 THEN CONCAT(pe.worker_id, "_", DATE(pe.from_time)) END), 0) AS late_days')
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

        if (Auth::check() && !Auth::user()->hasRole('Admin')) {
            $firmIds = Auth::user()->user_firms()->pluck('firm_id');
            $resultsForReport->whereHas('branch', function ($query) use ($firmIds) {
                $query->whereIn('firm_id', $firmIds);
            });
        }

        $paginatedWorkers = $resultsForReport->paginate($per_page);

        $workingDaysMap = $this->scheduleService->batchCalculateWorkingDays($paginatedWorkers->items(), $from, $to);

        foreach ($paginatedWorkers as $workerItem) {
            $workerItem->work_days = $workingDaysMap[$workerItem->id] ?? 0;
        }

        $firms = Firm::query()->without(['branches'])->select('id', 'name');
        $branches = Branch::query()->without(['firm', 'workers'])->select('id', 'firm_id', 'name');

        if (Auth::check() && !Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $firms->whereIn('id', $userFirms);
            $branches->whereIn('firm_id', $userFirms);
        }

        return [
            'worker' => $paginatedWorkers,
            'firms' => $firms->get(),
            'branches' => $branches->get(),
        ];
    }

    /**
     * Get data for Monthly Attendance Matrix/Grid (Web and Mobile API)
     */
    public function getAttendanceGridData(Request $request): array
    {
        $per_page = $request->per_page ? (int)$request->per_page : 15;

        if ($request->month) {
            $month = $request->month;
            $monthNumber = Carbon::parse($month)->month;
            $year = Carbon::parse($month)->year;
        } else {
            $month = date('Y-m');
            $monthNumber = (int)date('m');
            $year = (int)date('Y');
        }

        $monthStart = Carbon::create($year, $monthNumber, 1)->startOfMonth()->toDateTimeString();
        $monthEnd = Carbon::create($year, $monthNumber, 1)->endOfMonth()->toDateTimeString();

        $currentMonth = Carbon::now()->format('Y-m');
        $currentDay = Carbon::now()->day;

        if ($month === $currentMonth || empty($month)) {
            $daysInMonth = $currentDay;
        } else {
            $daysInMonth = Carbon::create($year, $monthNumber, 1)->daysInMonth;
        }

        $workers = Worker::with([
            'HikvisionAccessEvents' => function ($query) use ($monthStart, $monthEnd) {
                $query->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->whereIn('attendanceStatus', AttendanceStatus::inValues());
            }
        ]);

        if ($request->firm_id) {
            $workers = $workers->whereHas('branch', function ($query) use ($request) {
                $query->where('firm_id', $request->firm_id);
            });
        }

        if ($request->branch_id) {
            $workers = $workers->where('branch_id', $request->branch_id);
        }

        if (Auth::check() && !Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $workers = $workers->whereHas('branch', function ($query) use ($userFirms) {
                $query->whereIn('firm_id', $userFirms);
            });
        }

        $workers = $workers->paginate($per_page);

        $holidaysMap = $this->scheduleService->batchGetHolidays($workers->items(), $month);
        foreach ($workers as $worker) {
            $worker->holidays = $holidaysMap[$worker->id] ?? [];
        }

        $firms = Firm::query()->without(['branches'])->select('id', 'name');
        $branches = Branch::query()->without(['firm', 'workers'])->select('id', 'firm_id', 'name');

        if (Auth::check() && !Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $firms->whereIn('id', $userFirms);
            $branches->whereIn('firm_id', $userFirms);
        }

        return [
            'worker' => $workers,
            'daysInMonth' => $daysInMonth,
            'firms' => $firms->get(),
            'branches' => $branches->get(),
        ];
    }

    /**
     * Get data for Daily Attendance (Web and Mobile API)
     */
    public function getDailyAttendanceData(Request $request, Branch $branch): array
    {
        /** @var \App\Models\User\User|null $user */
        $user = Auth::user();
        if ($user && !$user->hasRole('Admin') && !$user->hasBranchAccess($branch)) {
            abort(403, 'Unauthorized access to this branch.');
        }

        $per_page = $request->per_page ? (int)$request->per_page : 15;
        $date = $request->date ?: date('Y-m-d');

        $workers = Worker::with([
            'HikvisionAccessEvents' => function ($query) use ($date) {
                $query->whereBetween('created_at', ["{$date} 00:00:00", "{$date} 23:59:59"])
                    ->whereIn('attendanceStatus', AttendanceStatus::allValues());
            }
        ])
            ->where('branch_id', '=', $branch->id);

        if ($user && !$user->hasRole('Admin')) {
            $userFirms = $user->user_firms()->pluck('firm_id');
            $workers = $workers->whereHas('branch', function ($query) use ($userFirms) {
                $query->whereIn('firm_id', $userFirms);
            });
        }

        $workers = $workers->paginate($per_page);

        $subReq = clone $request;
        $nextDay = Carbon::parse($date)->addDay()->format('Y-m-d');
        $subReq->merge([
            'branch_id' => $branch->id,
            'from' => $date,
            'to' => $nextDay,
        ]);
        $pairedEvents = $this->pairingService
            ->buildPairedEventsQuery($subReq, $date, $nextDay)
            ->whereRaw("DATE(pe.from_time) = ?", [$date])
            ->get();
        $pairedByWorker = $pairedEvents->groupBy('worker_id');

        foreach ($workers as $worker) {
            $workerPairs = $pairedByWorker->get($worker->id, collect());
            $worker->paired_events = $workerPairs->values()->toArray();
            $worker->worked_minutes = (int) $workerPairs->sum('worked_minutes');
            $worker->late_minutes = (int) ($workerPairs->max('late_minutes') ?? 0);
        }

        return [
            'worker' => $workers,
            'branch' => $branch,
        ];
    }

    protected function countWorkingDays(Request $request): int
    {
        $workerId = $request->worker_id > 0 ? (int)$request->worker_id : null;
        $branchId = $request->branch_id > 0 ? (int)$request->branch_id : null;
        $from = Carbon::parse($request->from)->startOfDay();
        $to = Carbon::parse($request->to)->startOfDay();

        if ($workerId) {
            $worker = Worker::with(['branch'])->find($workerId);
            if ($worker) {
                return $this->scheduleService->countWorkingDays($worker, $from, $to);
            }
        }

        return $this->scheduleService->countWorkingDaysForBranch($branchId, $from, $to);
    }
}
