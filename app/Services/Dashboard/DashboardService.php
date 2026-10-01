<?php

namespace App\Services\Dashboard;

use App\Enums\AttendanceStatus;
use App\Models\Branch\Branch;
use App\Models\Firm\Firm;
use App\Models\Worker\Worker;
use App\Services\Attendance\AttendancePairingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        protected AttendancePairingService $pairingService
    ) {}

    /**
     * Calculate dashboard data and stats.
     */
    public function getDashboardData(Request $request): array
    {
        $user = Auth::user();
        $isAdmin = $user && $user->hasRole('Admin');

        $month = $request->month ?: date('Y-m');
        $monthNumber = Carbon::parse($month)->month;
        $year = Carbon::parse($month)->year;

        // Base worker query with permissions
        $workersQuery = Worker::query()->where('workers.status', 1);

        if (!$isAdmin && $user) {
            $userFirms = $user->user_firms()->pluck('firm_id');
            $workersQuery->whereHas('branch', function ($query) use ($userFirms) {
                $query->whereIn('firm_id', $userFirms);
            });
        }

        if ($request->branch_id) {
            $workersQuery->where('workers.branch_id', $request->branch_id);
        }

        if ($request->firm_id) {
            $workersQuery->whereHas('branch', function ($query) use ($request) {
                $query->where('firm_id', $request->firm_id);
            });
        }

        $allWorker = (clone $workersQuery)->count('workers.id');

        // Today's boundaries
        $todayDate = date('Y-m-d');
        $todayStart = Carbon::today()->startOfDay()->toDateTimeString();
        $todayEnd = Carbon::today()->endOfDay()->toDateTimeString();

        // 2. Today's First Event (On Time vs Late) - Grouped strictly by employeeNoString
        $todayFirstEvents = DB::table('hikvision_access_events as hae')
            ->select(
                'hae.employeeNoString',
                DB::raw('MIN(hae.created_at) as first_created_at')
            )
            ->whereNull('hae.deleted_at')
            ->whereBetween('hae.created_at', [$todayStart, $todayEnd])
            ->whereIn('hae.attendanceStatus', AttendanceStatus::inValues())
            ->groupBy('hae.employeeNoString');

        $result = (clone $workersQuery)
            ->joinSub($todayFirstEvents, 'first_event', function ($join) {
                $join->on('workers.employeeNoString', '=', 'first_event.employeeNoString');
            })
            ->whereNotNull('first_event.first_created_at')
            ->selectRaw('
                COALESCE(SUM(CASE WHEN TIME(first_event.first_created_at) <= TIME(workers.work_time) THEN 1 ELSE 0 END), 0) AS on_time,
                COALESCE(SUM(CASE WHEN TIME(first_event.first_created_at) > TIME(workers.work_time) THEN 1 ELSE 0 END), 0) AS late
            ')
            ->first();

        $onTime = (int) ($result->on_time ?? 0);
        $late = (int) ($result->late ?? 0);
        $cameCount = $onTime + $late;

        // 3. On holiday today
        $onHolidayWorkersQuery = (clone $workersQuery)
            ->whereHas('worker_holidays', function ($query) use ($todayDate) {
                $query->where('from', '<=', $todayDate)
                    ->where('to', '>=', $todayDate);
            });

        $onHoliday = (clone $onHolidayWorkersQuery)->distinct('workers.id')->count('workers.id');

        // On holiday and did NOT check in today (to avoid double deduction when subtracting from absent)
        $onHolidayAbsent = (clone $onHolidayWorkersQuery)
            ->whereDoesntHave('HikvisionAccessEvents', function ($query) use ($todayStart, $todayEnd) {
                $query->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereIn('attendanceStatus', AttendanceStatus::inValues());
            })
            ->distinct('workers.id')
            ->count('workers.id');

        // 4. Absent (Not checked in today and not on holiday)
        $notCome = max(0, $allWorker - $cameCount - $onHolidayAbsent);

        // 5. Currently in building vs Gone (determined by latest event today per worker)
        $latestSub = DB::table('hikvision_access_events')
            ->select('employeeNoString', DB::raw('MAX(created_at) as max_created_at'))
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->groupBy('employeeNoString');

        $todayLatestEvents = DB::table('hikvision_access_events as hae')
            ->joinSub($latestSub, 'latest', function ($join) {
                $join->on('hae.employeeNoString', '=', 'latest.employeeNoString')
                    ->on('hae.created_at', '=', 'latest.max_created_at');
            })
            ->whereNull('hae.deleted_at')
            ->whereBetween('hae.created_at', [$todayStart, $todayEnd])
            ->select('hae.employeeNoString', 'hae.attendanceStatus');

        $inSql = AttendanceStatus::inSqlList();
        $outSql = AttendanceStatus::outSqlList();

        $latestStatusResult = (clone $workersQuery)
            ->joinSub($todayLatestEvents, 'le', function ($join) {
                $join->on('workers.employeeNoString', '=', 'le.employeeNoString');
            })
            ->selectRaw("
                COALESCE(SUM(CASE WHEN le.attendanceStatus IN ({$inSql}) THEN 1 ELSE 0 END), 0) AS in_building,
                COALESCE(SUM(CASE WHEN le.attendanceStatus IN ({$outSql}) THEN 1 ELSE 0 END), 0) AS gone
            ")
            ->first();

        $inBuilding = (int) ($latestStatusResult->in_building ?? 0);
        $gone = (int) ($latestStatusResult->gone ?? 0);

        $stats = [
            'all_worker' => (int) $allWorker,
            'absent' => (int) $notCome,
            'on_holiday' => (int) $onHoliday,
            'on_time' => (int) $onTime,
            'late' => (int) $late,
            'in_building' => (int) $inBuilding,
            'gone' => (int) $gone,
        ];

        // 6. Monthly/Range chart stats via unified AttendancePairingService
        if ($request->from && $request->to) {
            $from = $request->from;
            $to = $request->to;
        } else {
            $from = Carbon::parse($month)->startOfMonth()->format('Y-m-d');
            $to = Carbon::parse($month)->endOfMonth()->format('Y-m-d');
        }

        $pairedEvents = $this->pairingService->buildPairedEventsQuery($request, $from, $to);

        $resultsForHisobot = DB::table(DB::raw("({$pairedEvents->toSql()}) as paired_events"))
            ->mergeBindings($pairedEvents)
            ->select(
                DB::raw('DATE(from_time) as worked_date'),
                DB::raw('COALESCE(SUM(worked_minutes), 0) / 60 as worked_hours'),
                DB::raw('0 as break_hours'),
                DB::raw('COALESCE(SUM(IF(late_minutes > 0, late_minutes, 0)), 0) / 60 as late_hours')
            )
            ->groupBy(DB::raw('DATE(from_time)'))
            ->orderBy('worked_date')
            ->get();

        // 7. Dropdown filters (lean payload)
        $firms = Firm::query()->without(['branches'])->select('id', 'name');
        $branches = Branch::query()->without(['firm', 'workers'])->select('id', 'firm_id', 'name');

        if (!$isAdmin && $user) {
            $userFirms = $user->user_firms()->pluck('firm_id');
            $firms->whereIn('id', $userFirms);
            $branches->whereIn('firm_id', $userFirms);
        }

        return [
            'stats' => $stats,
            'resultsForHisobot' => $resultsForHisobot,
            'firms' => $firms->get(),
            'branches' => $branches->get(),
            'today' => $todayDate,
            'month' => $month,
        ];
    }
}
