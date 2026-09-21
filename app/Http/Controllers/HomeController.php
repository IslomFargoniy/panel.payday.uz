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
            $month = date('Y-m'); // '2025-05'
            $monthNumber = date('m'); // '05'
            $year = date('Y'); // '2025'
        }

        $allWorker = Worker::with([]);

        if (!Auth::user()->hasRole('Admin')) {
            $allWorker = $allWorker->whereHas('branch', function ($query) {
                $query->whereHas('firm', function ($query) {
                    $query->whereHas('user_firms', function ($query) {
                        $query->where('user_id', Auth::id());
                    });
                });
            });
        }

        if ($request->branch_id) {
            $allWorker = $allWorker->where('branch_id', $request->branch_id);
        }

        if ($request->firm_id) {
            $allWorker = $allWorker->whereHas('branch', function ($query) use ($request) {
                $query->where('firm_id', $request->firm_id);
            });
        }

        $allWorker = $allWorker->count('workers.id');


        $notCome = Worker::with([])
            ->leftJoin('hikvision_access_events as hae', function ($join) {
                $join->on('hae.employeeNoString', '=', 'workers.employeeNoString')
                    ->whereRaw('CURDATE() = DATE(hae.created_at)');
            })
            ->whereNull('hae.id');

        if (!Auth::user()->hasRole('Admin')) {
            $notCome = $notCome->whereHas('branch', function ($query) {
                $query->whereHas('firm', function ($query) {
                    $query->whereHas('user_firms', function ($query) {
                        $query->where('user_id', Auth::id());
                    });
                });
            });
        }

        if ($request->branch_id) {
            $notCome = $notCome->where('branch_id', $request->branch_id);
        }

        if ($request->firm_id) {
            $notCome = $notCome->whereHas('branch', function ($query) use ($request) {
                $query->where('firm_id', $request->firm_id);
            });
        }

        $notCome = $notCome->count('workers.id');


        $onHoliday = Worker::with([])
            ->whereHas('worker_holidays' , function ($query) {
                $query->whereDate(DB::raw('CURDATE()'), '>=', DB::raw('`from`'))
                    ->whereDate(DB::raw('CURDATE()'), '<=', DB::raw('`to`'));
            })
            ->groupBy('workers.id')
            ->distinct('workers.id');

        if (!Auth::user()->hasRole('Admin')) {
            $onHoliday = $onHoliday->whereHas('branch', function ($query) {
                $query->whereHas('firm', function ($query) {
                    $query->whereHas('user_firms', function ($query) {
                        $query->where('user_id', Auth::id());
                    });
                });
            });
        }

        if ($request->branch_id) {
            $onHoliday = $onHoliday->where('branch_id', $request->branch_id);
        }

        if ($request->firm_id) {
            $onHoliday = $onHoliday->whereHas('branch', function ($query) use ($request) {
                $query->where('firm_id', $request->firm_id);
            });
        }

        $onHoliday = $onHoliday->count('workers.id');


        $result = Worker::with([])
            ->join(DB::raw('(
        SELECT hae.*
        FROM hikvision_access_events hae
        WHERE DATE(hae.created_at) = CURDATE()
        AND hae.attendanceStatus IN ("CheckIn", "entered")
        AND hae.created_at = (
            SELECT MIN(created_at)
            FROM hikvision_access_events
            WHERE DATE(created_at) = CURDATE()
            AND employeeNoString = hae.employeeNoString
            AND attendanceStatus IN ("CheckIn", "entered")
        )
        GROUP BY DATE(hae.created_at), hae.employeeNoString
    ) AS first_event'), 'workers.employeeNoString', '=', 'first_event.employeeNoString')
            ->selectRaw('
        SUM(CASE WHEN TIME(first_event.created_at) <= TIME(first_event.work_time) THEN 1 ELSE 0 END) AS on_time,
        SUM(CASE WHEN TIME(first_event.created_at) > TIME(first_event.work_time) THEN 1 ELSE 0 END) AS late
    ');

        if (!Auth::user()->hasRole('Admin')) {
            $result = $result->whereHas('branch', function ($query) {
                $query->whereHas('firm', function ($query) {
                    $query->whereHas('user_firms', function ($query) {
                        $query->where('user_id', Auth::id());
                    });
                });
            });
        }

        if ($request->branch_id) {
            $result = $result->where('branch_id', $request->branch_id);
        }

        if ($request->firm_id) {
            $result = $result->whereHas('branch', function ($query) use ($request) {
                $query->where('firm_id', $request->firm_id);
            });
        }

        $result = $result->first();

        $gone = Worker::with([])
            ->join(DB::raw('(
            SELECT hae.*
            FROM hikvision_access_events hae
            WHERE DATE(hae.created_at) = CURDATE()
              AND hae.attendanceStatus IN ("checkOut")
              AND hae.created_at = (
                  SELECT MAX(created_at)
                  FROM hikvision_access_events
                  WHERE DATE(created_at) = CURDATE()
                    AND employeeNoString = hae.employeeNoString
              )
            GROUP BY DATE(hae.created_at), hae.employeeNoString
        ) AS first_event'), 'workers.employeeNoString', '=', 'first_event.employeeNoString');


        if (!Auth::user()->hasRole('Admin')) {
            $gone = $gone->whereHas('branch', function ($query) {
                $query->whereHas('firm', function ($query) {
                    $query->whereHas('user_firms', function ($query) {
                        $query->where('user_id', Auth::id());
                    });
                });
            });
        }

        if ($request->branch_id) {
            $gone = $gone->where('branch_id', $request->branch_id);
        }

        if ($request->firm_id) {
            $gone = $gone->whereHas('branch', function ($query) use ($request) {
                $query->where('firm_id', $request->firm_id);
            });
        }

        $gone = $gone->count();


        $onTime = $result->on_time ?? 0;
        $late = $result->late ?? 0;

        $stats = [
            'all_worker' => $allWorker ?? 0,
            'absent' => $notCome ?? 0,
            'on_holiday' => $onHoliday ?? 0,
            'on_time' => (int) $onTime,
            'late' => (int) $late,
            'gone' => $gone ?? 0,
        ];


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

        // Final select Hisobot
        $resultsForHisobot = DB::table(DB::raw("({$pairedEvents->toSql()}) as paired_events"))
            ->mergeBindings($pairedEvents)
            ->select(
                DB::raw('date(from_time) as worked_date'),
                DB::raw('sum(worked_minutes) / 60 as worked_hours'),
                DB::raw('sum(break_minutes) / 60 as break_hours'),
                DB::raw('sum(IF(late_minutes > 0, late_minutes, 0)) / 60 as late_hours')
            )
            ->groupBy(DB::raw('date(from_time)'))
            ->orderBy('from_time')
            ->get();


        $firms = Firm::with([]);
        $branches = Branch::with([]);

        if (!Auth::user()->hasRole('Admin')) {
            $firms->whereHas('user_firms', function ($query) {
                $query->where('user_id', Auth::id());
            });

            $branches = $branches->whereHas('firm', function ($query) {
                $query->whereHas('user_firms', function ($query) {
                    $query->where('user_id', Auth::id());
                });
            });
        }

        $firms = $firms->get();
        $branches = $branches->get();

        return Inertia::render('dashboard', [
            'stats' => $stats,
            'daily_stats' => $resultsForHisobot,
            'firms' => $firms,
            'branches' => $branches,
        ]);


    }
}
