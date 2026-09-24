<?php

namespace App\Http\Controllers;

use App\Models\Branch\Branch;
use App\Models\Firm\Firm;
use App\Models\Worker\Worker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->month) {
            $month = $request->month;
            $monthNumber = Carbon::parse($month)->month;
            $year = Carbon::parse($month)->year;
        } else {
            $month = date('Y-m');
            $monthNumber = (int)date('m');
            $year = (int)date('Y');
        }

        $todayStart = Carbon::today()->startOfDay()->toDateTimeString();
        $todayEnd = Carbon::today()->endOfDay()->toDateTimeString();
        $todayDate = Carbon::today()->toDateString();

        // 1. All Workers base query
        $workersQuery = Worker::query()->without(['branch']);

        if ($request->branch_id) {
            $workersQuery->where('branch_id', $request->branch_id);
        }

        if ($request->firm_id) {
            $workersQuery->whereHas('branch', function ($query) use ($request) {
                $query->where('firm_id', $request->firm_id);
            });
        }

        if (!Auth::user()->hasRole('Admin')) {
            $userFirmIds = Auth::user()->user_firms()->pluck('firm_id');
            $workersQuery->whereHas('branch', function ($query) use ($userFirmIds) {
                $query->whereIn('firm_id', $userFirmIds);
            });
        }

        $allWorker = (clone $workersQuery)->count('workers.id');

        // 2. Today's First Event (On Time vs Late) - Fast MIN() with GROUP BY
        $todayFirstEvents = DB::table('hikvision_access_events as hae')
            ->join('workers as w', 'w.employeeNoString', '=', 'hae.employeeNoString')
            ->select(
                'hae.employeeNoString',
                DB::raw('COALESCE(hae.work_time, w.work_time) as work_time'),
                DB::raw("MIN(CASE WHEN TIME(hae.created_at) >= '05:00:00' OR COALESCE(hae.work_time, w.work_time) < '06:00:00' THEN hae.created_at END) as first_created_at")
            )
            ->whereNull('hae.deleted_at')
            ->whereBetween('hae.created_at', [$todayStart, $todayEnd])
            ->whereIn('hae.attendanceStatus', ['keldi', 'CheckIn', 'checkIn', 'entered'])
            ->groupBy('hae.employeeNoString', DB::raw('COALESCE(hae.work_time, w.work_time)'));

        $result = (clone $workersQuery)
            ->joinSub($todayFirstEvents, 'first_event', function ($join) {
                $join->on('workers.employeeNoString', '=', 'first_event.employeeNoString');
            })
            ->whereNotNull('first_event.first_created_at')
            ->selectRaw('
                COALESCE(SUM(CASE WHEN TIME(first_event.first_created_at) <= TIME(first_event.work_time) THEN 1 ELSE 0 END), 0) AS on_time,
                COALESCE(SUM(CASE WHEN TIME(first_event.first_created_at) > TIME(first_event.work_time) THEN 1 ELSE 0 END), 0) AS late
            ')
            ->first();

        $onTime = (int) ($result->on_time ?? 0);
        $late = (int) ($result->late ?? 0);
        $cameCount = $onTime + $late;

        // 3. On holiday today - Uses index on worker_holidays (worker_id, from, to)
        $onHoliday = (clone $workersQuery)
            ->whereHas('worker_holidays', function ($query) use ($todayDate) {
                $query->where('from', '<=', $todayDate)
                    ->where('to', '>=', $todayDate);
            })
            ->distinct('workers.id')
            ->count('workers.id');

        // 4. Absent (Not checked in today and not on holiday)
        $notCome = max(0, $allWorker - $cameCount - $onHoliday);

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

        $latestStatusResult = (clone $workersQuery)
            ->joinSub($todayLatestEvents, 'le', function ($join) {
                $join->on('workers.employeeNoString', '=', 'le.employeeNoString');
            })
            ->selectRaw('
                COALESCE(SUM(CASE WHEN le.attendanceStatus IN (\'keldi\', \'CheckIn\', \'checkIn\', \'entered\') THEN 1 ELSE 0 END), 0) AS in_building,
                COALESCE(SUM(CASE WHEN le.attendanceStatus IN (\'ketdi\', \'checkOut\', \'CheckOut\', \'exited\') THEN 1 ELSE 0 END), 0) AS gone
            ')
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

        // 6. Monthly/Range chart stats via single-pass window function query
        $eventsWithLead = DB::table('hikvision_access_events as hae')
            ->select(
                'hae.id',
                'w.name as worker',
                'b.name as branch',
                'w.branch_id',
                'b.firm_id',
                DB::raw('COALESCE(hae.work_time, w.work_time) as work_time'),
                'f.name as firm',
                'hae.attendanceStatus as status_from',
                'hae.label as label_from',
                'hae.created_at as from_time',
                DB::raw("LEAD(hae.created_at) OVER (PARTITION BY hae.employeeNoString ORDER BY hae.created_at) AS to_time"),
                DB::raw("LEAD(hae.attendanceStatus) OVER (PARTITION BY hae.employeeNoString ORDER BY hae.created_at) AS status_to"),
                DB::raw("LEAD(hae.label) OVER (PARTITION BY hae.employeeNoString ORDER BY hae.created_at) AS label_to"),
                DB::raw("MIN(CASE WHEN hae.attendanceStatus IN ('keldi', 'CheckIn', 'checkIn', 'entered') AND (TIME(hae.created_at) >= '05:00:00' OR COALESCE(hae.work_time, w.work_time) < '06:00:00') THEN hae.created_at END) OVER (PARTITION BY hae.employeeNoString, DATE(hae.created_at)) AS day_first_check_in"),
                DB::raw("MIN(CASE WHEN hae.attendanceStatus IN ('keldi', 'CheckIn', 'checkIn', 'entered') AND (TIME(hae.created_at) >= '05:00:00' OR COALESCE(hae.work_time, w.work_time) < '06:00:00') THEN COALESCE(hae.work_time, w.work_time) END) OVER (PARTITION BY hae.employeeNoString, DATE(hae.created_at)) AS day_first_work_time")
            )
            ->join('workers as w', 'w.employeeNoString', '=', 'hae.employeeNoString')
            ->join('branches as b', 'w.branch_id', '=', 'b.id')
            ->join('firms as f', 'b.firm_id', '=', 'f.id')
            ->whereNull('hae.deleted_at');

        if ($request->from && $request->to) {
            $eventsWithLead->whereBetween('hae.created_at', [$request->from, $request->to . " 23:59:59"]);
        } else {
            $eventsWithLead->whereMonth('hae.created_at', $monthNumber)
                ->whereYear('hae.created_at', $year);
        }

        if ($request->branch_id) {
            $eventsWithLead->where('b.id', $request->branch_id);
        }
        if ($request->firm_id) {
            $eventsWithLead->where('f.id', $request->firm_id);
        }

        if (!Auth::user()->hasRole('Admin')) {
            $skladIds = Auth::user()->user_firms()->pluck('firm_id');
            $eventsWithLead->whereIn('f.id', $skladIds);
        }

        $pairedEvents = DB::table(DB::raw("({$eventsWithLead->toSql()}) as pe"))
            ->mergeBindings($eventsWithLead)
            ->where(function ($q) {
                $q->whereIn('pe.status_from', ['keldi', 'CheckIn', 'checkIn', 'entered'])
                  ->where(function ($sub) {
                      $sub->whereIn('pe.status_to', ['ketdi', 'CheckOut', 'checkOut', 'exited'])
                          ->orWhereNull('pe.status_to')
                          ->orWhere(function ($cross) {
                              $cross->whereIn('pe.status_to', ['keldi', 'CheckIn', 'checkIn', 'entered'])
                                    ->whereRaw('DATE(pe.to_time) != DATE(pe.from_time)');
                          });
                  });
            })
            ->select(
                'pe.worker',
                'pe.branch',
                'pe.branch_id',
                'pe.firm_id',
                'pe.work_time',
                'pe.firm',
                'pe.from_time',
                DB::raw('IF(pe.status_to IN ("ketdi", "CheckOut", "checkOut", "exited"), pe.to_time, NULL) as to_time'),
                'pe.status_from',
                DB::raw('IF(pe.status_to IN ("ketdi", "CheckOut", "checkOut", "exited"), CONCAT(pe.label_from, "/", pe.label_to), pe.label_from) as status'),
                DB::raw("CASE
                    WHEN ROW_NUMBER() OVER (
                        PARTITION BY pe.employeeNoString, DATE(pe.from_time) 
                        ORDER BY (TIME(pe.from_time) < '05:00:00'), pe.from_time
                    ) = 1 AND pe.day_first_check_in IS NOT NULL AND TIME(pe.day_first_check_in) > TIME(pe.day_first_work_time)
                    THEN GREATEST(1, TIMESTAMPDIFF(MINUTE, TIMESTAMP(DATE(pe.from_time), pe.day_first_work_time), pe.day_first_check_in))
                    ELSE 0
                END as late_minutes"),
                DB::raw('IF(pe.status_to IN ("ketdi", "CheckOut", "checkOut", "exited"), TIMESTAMPDIFF(MINUTE, pe.from_time, pe.to_time), 0) as worked_minutes')
            );

        $resultsForHisobot = DB::table(DB::raw("({$pairedEvents->toSql()}) as paired_events"))
            ->mergeBindings($pairedEvents)
            ->select(
                DB::raw('DATE(from_time) as worked_date'),
                DB::raw('COALESCE(SUM(worked_minutes), 0) / 60 as worked_hours'),
                DB::raw('0 as break_hours'),
                DB::raw('COALESCE(SUM(IF(late_minutes > 0, late_minutes, 0)), 0) / 60 as late_hours')
            )
            ->groupBy(DB::raw('DATE(from_time)'))
            ->orderBy('from_time')
            ->get();

        // 7. Dropdown filters (lean payload)
        $firms = Firm::query()->without(['branches'])->select('id', 'name');
        $branches = Branch::query()->without(['firm', 'workers'])->select('id', 'firm_id', 'name');

        if (!Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $firms->whereIn('id', $userFirms);
            $branches->whereIn('firm_id', $userFirms);
        }

        return Inertia::render('dashboard', [
            'stats' => $stats,
            'daily_stats' => $resultsForHisobot,
            'firms' => $firms->get(),
            'branches' => $branches->get(),
        ]);
    }
}
