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

        // 2. Absent (Not come today) - Uses index seek on created_at range
        $notCome = (clone $workersQuery)
            ->leftJoin('hikvision_access_events as hae', function ($join) use ($todayStart, $todayEnd) {
                $join->on('hae.employeeNoString', '=', 'workers.employeeNoString')
                    ->whereBetween('hae.created_at', [$todayStart, $todayEnd]);
            })
            ->whereNull('hae.id')
            ->count('workers.id');

        // 3. On holiday today - Uses index on worker_holidays (worker_id, from, to)
        $onHoliday = (clone $workersQuery)
            ->whereHas('worker_holidays', function ($query) use ($todayDate) {
                $query->where('from', '<=', $todayDate)
                    ->where('to', '>=', $todayDate);
            })
            ->distinct('workers.id')
            ->count('workers.id');

        // 4. Today's First Event (On Time vs Late) - Fast MIN() with GROUP BY
        $todayFirstEvents = DB::table('hikvision_access_events')
            ->select('employeeNoString', 'work_time', DB::raw('MIN(created_at) as first_created_at'))
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->whereIn('attendanceStatus', ['keldi', 'CheckIn', 'entered'])
            ->groupBy('employeeNoString', 'work_time');

        $result = (clone $workersQuery)
            ->joinSub($todayFirstEvents, 'first_event', function ($join) {
                $join->on('workers.employeeNoString', '=', 'first_event.employeeNoString');
            })
            ->selectRaw('
                COALESCE(SUM(CASE WHEN TIME(first_event.first_created_at) <= TIME(first_event.work_time) THEN 1 ELSE 0 END), 0) AS on_time,
                COALESCE(SUM(CASE WHEN TIME(first_event.first_created_at) > TIME(first_event.work_time) THEN 1 ELSE 0 END), 0) AS late
            ')
            ->first();

        // 5. Gone today (Checked out) - Fast distinct check
        $todayCheckouts = DB::table('hikvision_access_events')
            ->select('employeeNoString')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->whereIn('attendanceStatus', ['ketdi', 'checkOut', 'exited'])
            ->distinct();

        $gone = (clone $workersQuery)
            ->joinSub($todayCheckouts, 'last_event', function ($join) {
                $join->on('workers.employeeNoString', '=', 'last_event.employeeNoString');
            })
            ->count('workers.id');

        $stats = [
            'all_worker' => (int) $allWorker,
            'absent' => (int) $notCome,
            'on_holiday' => (int) $onHoliday,
            'on_time' => (int) ($result->on_time ?? 0),
            'late' => (int) ($result->late ?? 0),
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
                'hae.work_time',
                'f.name as firm',
                'hae.attendanceStatus as status_from',
                'hae.label as label_from',
                'hae.created_at as from_time',
                DB::raw("LEAD(hae.created_at) OVER (PARTITION BY hae.employeeNoString ORDER BY hae.created_at) AS to_time"),
                DB::raw("LEAD(hae.attendanceStatus) OVER (PARTITION BY hae.employeeNoString ORDER BY hae.created_at) AS status_to"),
                DB::raw("LEAD(hae.label) OVER (PARTITION BY hae.employeeNoString ORDER BY hae.created_at) AS label_to")
            )
            ->join('workers as w', 'w.employeeNoString', '=', 'hae.employeeNoString')
            ->join('branches as b', 'w.branch_id', '=', 'b.id')
            ->join('firms as f', 'b.firm_id', '=', 'f.id');

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
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereIn('pe.status_from', ['keldi', 'CheckIn', 'entered'])
                        ->whereIn('pe.status_to', ['ketdi', 'CheckOut', 'exited']);
                })->orWhere(function ($q) {
                    $q->whereIn('pe.status_from', ['Obetga ketdi', 'BreakOut'])
                        ->whereIn('pe.status_to', ['Obetdan keldi', 'BreakIn']);
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
                'pe.to_time',
                'pe.status_from',
                DB::raw("CONCAT(pe.label_from, '/', pe.label_to) as status"),
                DB::raw("CASE
                    WHEN pe.status_from IN ('keldi', 'CheckIn', 'entered')
                    THEN TIMESTAMPDIFF(MINUTE, TIMESTAMP(DATE(pe.from_time), pe.work_time), pe.from_time)
                    ELSE 0 END as late_minutes"),
                DB::raw("CASE
                    WHEN pe.status_from IN ('keldi', 'CheckIn', 'entered')
                    THEN TIMESTAMPDIFF(MINUTE, pe.from_time, pe.to_time)
                    ELSE 0 END as worked_minutes"),
                DB::raw("CASE
                    WHEN pe.status_from IN ('Obetga ketdi', 'BreakOut')
                    THEN TIMESTAMPDIFF(MINUTE, pe.from_time, pe.to_time)
                    ELSE 0 END as break_minutes")
            );

        $resultsForHisobot = DB::table(DB::raw("({$pairedEvents->toSql()}) as paired_events"))
            ->mergeBindings($pairedEvents)
            ->select(
                DB::raw('DATE(from_time) as worked_date'),
                DB::raw('COALESCE(SUM(worked_minutes), 0) / 60 as worked_hours'),
                DB::raw('COALESCE(SUM(break_minutes), 0) / 60 as break_hours'),
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
