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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use function Pest\Laravel\from;

class ReportController extends Controller
{
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


            if ($request->per_page) {
                $per_page = $request->per_page;
            } else {
                $per_page = 15;
            }

            $from = $request->from ?: Carbon::now()->startOfMonth()->toDateString();
            $to = $request->to ?: Carbon::now()->toDateString();
            $request->merge(['from' => $from, 'to' => $to]);

            $days = $this->countWorkingDays($request);


            $baseQuery = DB::table('hikvision_access_events as hae')
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
                    'hae.attendanceStatus',
                    'hae.label',
                    'hae.created_at',
                    DB::raw("ROW_NUMBER() OVER (PARTITION BY w.name ORDER BY hae.created_at) AS rn")
                )
                ->join('workers as w', 'w.employeeNoString', '=', 'hae.employeeNoString')
                ->join('branches as b', 'w.branch_id', '=', 'b.id')
                ->join('firms as f', 'b.firm_id', '=', 'f.id');

            $baseQuery->whereBetween('hae.created_at', [$from, $to . " 23:59:59"]);
            if ($request->worker_id) {
                $baseQuery->where('w.id', $request->worker_id);
            }
            if ($request->search) {
                $baseQuery->where(function ($query) use ($request) {
                    $query->where('w.name', 'like', '%' . $request->search . '%')
                        ->orWhere('b.name', 'like', '%' . $request->search . '%')
                        ->orWhere('f.name', 'like', '%' . $request->search . '%')
                        ->orWhere('hae.label', 'like', '%' . $request->search . '%');
                });
            }
            if ($request->branch_id) {
                $baseQuery->where('b.id', $request->branch_id);
            }
            if ($request->firm_id) {
                $baseQuery->where('f.id', $request->firm_id);
            }

// Non-admin users must be part of the firm
            if (!Auth::user()->hasRole('Admin')) {
                $firmIds = Auth::user()->user_firms()->pluck('firm_id');
                $baseQuery->whereIn('f.id', $firmIds);
            }

// Create derived tables with aliases e1 and e2
            $e1 = DB::raw("({$baseQuery->toSql()}) as e1");
            $e2 = DB::raw("({$baseQuery->toSql()}) as e2");

// Build paired events
            $pairedEvents = DB::table($e1)
                ->mergeBindings($baseQuery)
                ->join($e2, function ($join) {
                    $join->on('e1.worker', '=', 'e2.worker')
                        ->whereRaw('e2.rn = e1.rn + 1');
                })
                ->mergeBindings($baseQuery)
                ->where(function ($query) {
                    $query->where(function ($q) {
                        $q->whereIn('e1.attendanceStatus', ['keldi', 'CheckIn', 'entered'])
                            ->whereIn('e2.attendanceStatus', ['ketdi', 'CheckOut', 'exited']);
                    })->orWhere(function ($q) {
                        $q->whereIn('e1.attendanceStatus', ['Obetga ketdi', 'BreakOut'])
                            ->whereIn('e2.attendanceStatus', ['Obetdan keldi', 'BreakIn']);
                    });
                })
                ->select(
                    'e1.employeeNoString',
                    'e1.worker_id',
                    'e1.worker',
                    'e1.phone',
                    'e1.branch',
                    'e1.work_time',
                    'e1.end_time',
                    'e1.firm',
                    DB::raw('e1.created_at as from_time'),
                    DB::raw('e2.created_at as to_time'),
                    DB::raw('e1.attendanceStatus as status_from'),
                    DB::raw("CONCAT(e1.label, '/', e2.label) as status"),
                    DB::raw("CASE
                                WHEN e1.attendanceStatus IN ('keldi', 'CheckIn', 'entered')
                                     AND NOT EXISTS (
                                         SELECT 1 FROM hikvision_access_events sub
                                         WHERE sub.employeeNoString = e1.employeeNoString
                                           AND DATE(sub.created_at) = DATE(e1.created_at)
                                           AND sub.created_at < e1.created_at
                                     )
                                THEN TIMESTAMPDIFF(MINUTE, TIMESTAMP(DATE(e1.created_at), e1.work_time), e1.created_at)
                                ELSE 0
                            END as late_minutes"),
                    DB::raw("CASE
                    WHEN e1.attendanceStatus IN ('keldi', 'CheckIn', 'entered')
                    THEN TIMESTAMPDIFF(MINUTE, e1.created_at, e2.created_at)
                    ELSE 0 END as worked_minutes"),
                    DB::raw("CASE
                    WHEN e1.attendanceStatus IN ('Obetga ketdi', 'BreakOut')
                    THEN TIMESTAMPDIFF(MINUTE, e1.created_at, e2.created_at)
                    ELSE 0 END as break_minutes")
                );

// Final select
            $results = DB::table(DB::raw("({$pairedEvents->toSql()}) as paired_events"))
                ->mergeBindings($pairedEvents)
                ->select(
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
                ->orderBy('from', 'desc')
                ->paginate($per_page);


// Final select Report
            $resultsForReport = DB::table(DB::raw("({$pairedEvents->toSql()}) as paired_events"))
                ->mergeBindings($pairedEvents)
                ->select(
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
                ->orderBy('from')
                ->get();

//            dd($resultsForReport);

            $groupedByDate = $resultsForReport->groupBy(function ($item) {
                return $item->worker . '|' . \Carbon\Carbon::parse($item->from)->format('Y-m-d');
            });

            $report = new \stdClass();
            $report->working_days = $days;
            $report->worked_days = $groupedByDate->count(); // count of unique worked days
            $report->worked_minutes = $resultsForReport->sum('worked_minutes');
            $report->break_minutes = $resultsForReport->sum('break_minutes');
            $report->late_minutes = $resultsForReport->sum('late_minutes');
            $report->late_days = $groupedByDate->filter(function ($items) {
                return $items->sum('late_minutes') > 0;
            })->count();
            if ($request->worker_id) {
                $worker = Worker::find($request->worker_id);
                $report->last_salary_date = $worker->salaries->max('to');
                $report->hour_price = $worker->hour_price;
                $report->fine_price = $worker->fine_price;
                $report->work_time = $worker->work_time;
                $report->end_time = $worker->end_time;
            }
            $report->from = $request->from;
            $report->to = $request->to;


            $firms = Firm::with([]);
            $branches = Branch::with([]);
            $workers = Worker::with([]);

            if (!Auth::user()->hasRole('Admin')) {
                $firms->whereHas('user_firms', function ($query) {
                    $query->where('user_id', Auth::id());
                });

                $branches = $branches->whereHas('firm', function ($query) {
                    $query->whereHas('user_firms', function ($query) {
                        $query->where('user_id', Auth::id());
                    });
                });

                $workers = $workers->whereHas('branch', function ($query) {
                    $query->whereHas('firm', function ($query) {
                        $query->whereHas('user_firms', function ($query) {
                            $query->where('user_id', Auth::id());
                        });
                    });
                });
            }

            $firms = $firms->get();
            $branches = $branches->get();
            $workers = $workers->get();

            return Inertia::render('salary_report/index', [
                'report' => $report,
                'attendance' => $results,
                'firms' => $firms,
                'branches' => $branches,
                'workers' => $workers,
            ]);

        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()],
            ]);
        }
    }


    public function countWorkingDays(Request $request): int
    {
        $workerId = $request->worker_id > 0 ? $request->worker_id : null;
        $branchId = $request->branch_id > 0 ? $request->branch_id : null;
        $firmId = $request->firm_id > 0 ? $request->firm_id : null;
        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);

        // Generate date range using CarbonPeriod
        $dates = collect(CarbonPeriod::create($from, $to))->map(fn($d) => $d->copy()->startOfDay());

        $worker = Worker::find($workerId);

        $effectiveBranchId = $branchId ?? $worker?->branch_id;

        $branch = WorkerDay::where('worker_id', $workerId)->get();

        if (count($branch) == 0) {
            $branch = BranchDay::where('branch_id', $effectiveBranchId)->get();
        }

        // Load branch working days
        $workingDayIndexes = $branch
            ? $branch->pluck('day.index')->toArray()
            : range(1, 7); // Default: all days are working days if branch not given

        return $dates->filter(function ($date) use ($workerId, $branchId, $firmId, $workingDayIndexes) {
            $dayOfWeek = $date->dayOfWeekIso;

            if (!in_array($dayOfWeek, $workingDayIndexes)) {
                return false;
            }

            $isWorkerOnHoliday = $workerId && WorkerHoliday::where('worker_id', $workerId)
                    ->whereDate('from', '<=', $date)
                    ->whereDate('to', '>=', $date)
                    ->exists();

            $isBranchHolidayWorker = $workerId && BranchHoliday::whereHas('branch', function ($q) use ($workerId) {
                    $q->whereHas('workers', function ($q) use ($workerId) {
                        $q->where('workers.id', $workerId);
                    });
                })
                    ->whereDate('date', $date)
                    ->exists();

            $isFirmHolidayWorker = $workerId && FirmHoliday::whereHas('firm', function ($q) use ($workerId) {
                    $q->whereHas('branches', function ($q) use ($workerId) {
                        $q->whereHas('workers', function ($q) use ($workerId) {
                            $q->where('workers.id', $workerId);
                        });
                    });
                })
                    ->whereDate('date', $date)
                    ->exists();


            $isBranchHoliday = $branchId && BranchHoliday::where('branch_id', $branchId)
                    ->whereDate('date', $date)
                    ->exists();

            $isFirmHolidayBranch = $branchId && FirmHoliday::whereHas('firm', function ($q) use ($branchId) {
                    $q->whereHas('branches', function ($q) use ($branchId) {
                        $q->where('branches.id', $branchId);
                    });
                })
                    ->whereDate('date', $date)
                    ->exists();


            $isFirmHoliday = $firmId && FirmHoliday::where('firm_id', $firmId)
                    ->whereDate('date', $date)
                    ->exists();

            return !$isWorkerOnHoliday && !$isBranchHoliday && !$isFirmHoliday && !$isBranchHolidayWorker && !$isFirmHolidayWorker && !$isFirmHolidayBranch;
        })->count();
    }


    public function monthly_attendance(Request $request)
    {
        if ($request->per_page) {
            $per_page = $request->per_page;
        } else {
            $per_page = 15;
        }

        if ($request->from && $request->to) {
            $from = $request->from;
            $to = $request->to;
        } else {
            $from = Carbon::now()->startOfMonth()->toDateString();
            $to = Carbon::now()->toDateString();
        }

//        $workers = Worker::with([
//            'HikvisionAccessEvents' => function ($query) use ($monthNumber, $year) {
//                $query->whereMonth('created_at', $monthNumber)
//                    ->whereYear('created_at', $year)
//                    ->where('attendanceStatus', "checkIn");
//            }
//        ])
//            ->select(
//                'workers.*'
//            );
//
//        if ($request->firm_id) {
//            $workers = $workers->whereHas('branch', function ($query) use ($request) {
//                $query->where('firm_id', $request->firm_id);
//            });
//        }
//
//        if ($request->branch_id) {
//            $workers = $workers->where('branch_id', $request->branch_id);
//        }


        $baseQuery = DB::table('hikvision_access_events as hae')
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
                'hae.attendanceStatus',
                'hae.label',
                'hae.created_at',
                DB::raw("ROW_NUMBER() OVER (PARTITION BY w.name ORDER BY hae.created_at) AS rn")
            )
            ->join('workers as w', 'w.employeeNoString', '=', 'hae.employeeNoString')
            ->join('branches as b', 'w.branch_id', '=', 'b.id')
            ->join('firms as f', 'b.firm_id', '=', 'f.id');

        $baseQuery->whereBetween('hae.created_at', [$from, $to . " 23:59:59"]);

        if ($request->search) {
            $baseQuery->where(function ($query) use ($request) {
                $query->where('w.name', 'like', '%' . $request->search . '%')
                    ->orWhere('w.phone', 'like', '%' . $request->search . '%')
                    ->orWhere('w.address', 'like', '%' . $request->search . '%')
                    ->orWhere('w.comment', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->branch_id) {
            $baseQuery->where('b.id', $request->branch_id);
        }
        if ($request->firm_id) {
            $baseQuery->where('f.id', $request->firm_id);
        }

// Non-admin users must be part of the firm
        if (!Auth::user()->hasRole('Admin')) {
            $firmIds = Auth::user()->user_firms()->pluck('firm_id');
            $baseQuery->whereIn('f.id', $firmIds);
        }

// Create derived tables with aliases e1 and e2
        $e1 = DB::raw("({$baseQuery->toSql()}) as e1");
        $e2 = DB::raw("({$baseQuery->toSql()}) as e2");

// Build paired events
        $pairedEvents = DB::table($e1)
            ->mergeBindings($baseQuery)
            ->join($e2, function ($join) {
                $join->on('e1.worker', '=', 'e2.worker')
                    ->whereRaw('e2.rn = e1.rn + 1');
            })
            ->mergeBindings($baseQuery)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereIn('e1.attendanceStatus', ['keldi', 'CheckIn', 'entered'])
                        ->whereIn('e2.attendanceStatus', ['ketdi', 'CheckOut', 'exited']);
                })->orWhere(function ($q) {
                    $q->whereIn('e1.attendanceStatus', ['Obetga ketdi', 'BreakOut'])
                        ->whereIn('e2.attendanceStatus', ['Obetdan keldi', 'BreakIn']);
                });
            })
            ->select(
                'e1.employeeNoString',
                'e1.worker_id',
                'e1.worker',
                'e1.phone',
                'e1.branch',
                'e1.work_time',
                'e1.end_time',
                'e1.firm',
                DB::raw('e1.created_at as from_time'),
                DB::raw('e2.created_at as to_time'),
                DB::raw('e1.attendanceStatus as status_from'),
                DB::raw("CONCAT(e1.label, '/', e2.label) as status"),
                DB::raw("CASE
                                WHEN e1.attendanceStatus IN ('keldi', 'CheckIn', 'entered')
                                     AND NOT EXISTS (
                                         SELECT 1 FROM hikvision_access_events sub
                                         WHERE sub.employeeNoString = e1.employeeNoString
                                           AND DATE(sub.created_at) = DATE(e1.created_at)
                                           AND sub.created_at < e1.created_at
                                     )
                                THEN TIMESTAMPDIFF(MINUTE, TIMESTAMP(DATE(e1.created_at), e1.work_time), e1.created_at)
                                ELSE 0
                            END as late_minutes"),
                DB::raw("CASE
                    WHEN e1.attendanceStatus IN ('keldi', 'CheckIn', 'entered')
                    THEN TIMESTAMPDIFF(MINUTE, e1.created_at, e2.created_at)
                    ELSE 0 END as worked_minutes"),
                DB::raw("CASE
                    WHEN e1.attendanceStatus IN ('Obetga ketdi', 'BreakOut')
                    THEN TIMESTAMPDIFF(MINUTE, e1.created_at, e2.created_at)
                    ELSE 0 END as break_minutes")
            );

// Final select Report
        $resultsForReport = Worker::with([])
            ->leftJoin(DB::raw("({$pairedEvents->toSql()}) as paired_events"), 'workers.id', '=', 'paired_events.worker_id')
            ->mergeBindings($pairedEvents)
            ->select(
                'workers.*',
                DB::raw('COALESCE(SUM(paired_events.worked_minutes), 0) AS worked_minutes'),
                DB::raw('COALESCE(SUM(paired_events.break_minutes), 0) AS break_minutes'),
                DB::raw('COALESCE(SUM(IF(paired_events.late_minutes > 0, paired_events.late_minutes, 0)), 0) AS late_minutes'),
                DB::raw('COALESCE(COUNT(DISTINCT DATE(paired_events.from_time)), 0) AS worked_days'),
                DB::raw('COALESCE(SUM(IF(paired_events.late_minutes > 0, 1, 0)), 0) AS late_days'),
                DB::raw("count_working_days(workers.id,'$from','$to') as work_days")
            )
            ->groupBy('workers.id')
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

            $resultsForReport = $resultsForReport
                ->whereHas('branch', function ($query) {
                    $query->whereHas('firm', function ($query) {
                        $query->whereHas('user_firms', function ($query) {
                            $query->where('user_id', Auth::id());
                        });
                    });
                });
        }

        $firms = $firms->get();
        $branches = $branches->get();

        $resultsForReport = $resultsForReport
            ->paginate($per_page);

        return Inertia::render('monthly_report/index', [
            'worker' => $resultsForReport,
            'firms' => $firms,
            'branches' => $branches,
        ]);
    }


}
