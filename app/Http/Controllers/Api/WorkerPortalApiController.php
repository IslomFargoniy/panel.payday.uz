<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch\BranchHoliday;
use App\Models\Firm\FirmHoliday;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\Salary\Salary;
use App\Models\Salary\SalaryPayment;
use App\Models\Worker\Worker;
use App\Models\Worker\WorkerHoliday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkerPortalApiController extends Controller
{
    /**
     * Get the authenticated worker model
     */
    private function getWorker(Request $request): ?Worker
    {
        $user = $request->user();
        if ($user instanceof Worker) {
            return $user;
        }

        // If admin is viewing as worker
        if ($request->filled('worker_id')) {
            return Worker::find($request->worker_id);
        }

        return null;
    }

    /**
     * Today's check-in / check-out status and live KPI
     */
    public function todayStatus(Request $request): JsonResponse
    {
        $worker = $this->getWorker($request);
        if (!$worker) {
            return response()->json(['success' => false, 'message' => 'Xodim topilmadi.'], 404);
        }

        $worker->load('branch');
        $today = Carbon::now()->toDateString();

        $checkIn = HikvisionAccessEvent::where('employeeNoString', $worker->employeeNoString)
            ->whereDate('created_at', $today)
            ->whereIn('attendanceStatus', ['checkIn', 'keldi', 'entered'])
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'asc')
            ->first();

        $checkOut = HikvisionAccessEvent::where('employeeNoString', $worker->employeeNoString)
            ->whereDate('created_at', $today)
            ->whereIn('attendanceStatus', ['checkOut', 'ketdi', 'exited'])
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->first();

        $status = 'Kelmadi';
        $lateMinutes = 0;
        $workedHours = 0.0;

        if ($checkIn) {
            $inTime = Carbon::parse($checkIn->created_at);
            $scheduleStart = $worker->work_time ? Carbon::parse($today . ' ' . $worker->work_time) : null;

            if ($scheduleStart && $inTime->greaterThan($scheduleStart)) {
                $status = 'Kechikkan';
                $lateMinutes = (int) $inTime->diffInMinutes($scheduleStart);
            } else {
                $status = 'Kelgan';
            }

            if ($checkOut) {
                $outTime = Carbon::parse($checkOut->created_at);
                $workedHours = round($outTime->diffInMinutes($inTime) / 60, 2);
            } else {
                // Currently in progress
                $workedHours = round(Carbon::now()->diffInMinutes($inTime) / 60, 2);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'worker' => [
                    'id' => $worker->id,
                    'name' => $worker->name,
                    'phone' => $worker->phone,
                    'employeeNoString' => $worker->employeeNoString,
                    'avatar' => $worker->avatar,
                    'branch_name' => $worker->branch?->name,
                    'work_time' => $worker->work_time ? substr($worker->work_time, 0, 5) : '09:00',
                    'end_time' => $worker->end_time ? substr($worker->end_time, 0, 5) : '18:00',
                ],
                'today' => $today,
                'has_checked_in' => !is_null($checkIn),
                'check_in_time' => $checkIn ? Carbon::parse($checkIn->created_at)->format('H:i') : null,
                'has_checked_out' => !is_null($checkOut),
                'check_out_time' => $checkOut ? Carbon::parse($checkOut->created_at)->format('H:i') : null,
                'status' => $status,
                'late_minutes' => $lateMinutes,
                'worked_hours' => $workedHours,
            ]
        ]);
    }

    /**
     * Monthly attendance calendar / breakdown
     */
    public function myAttendance(Request $request): JsonResponse
    {
        $worker = $this->getWorker($request);
        if (!$worker) {
            return response()->json(['success' => false, 'message' => 'Xodim topilmadi.'], 404);
        }

        $monthStr = $request->input('month', Carbon::now()->format('Y-m'));
        $start = Carbon::parse($monthStr)->startOfMonth();
        $end = Carbon::parse($monthStr)->endOfMonth();

        // 1. Get all events for the month
        $events = HikvisionAccessEvent::where('employeeNoString', $worker->employeeNoString)
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy(fn($item) => Carbon::parse($item->created_at)->toDateString());

        // 2. Holidays
        $workerHolidays = WorkerHoliday::where('worker_id', $worker->id)
            ->whereDate('from', '<=', $end)
            ->whereDate('to', '>=', $start)
            ->get();

        $branchHolidays = BranchHoliday::where('branch_id', $worker->branch_id)
            ->whereBetween('date', [$start, $end])
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->flip();

        $days = [];
        $totalWorkedMinutes = 0;
        $totalLateMinutes = 0;
        $totalDaysPresent = 0;

        $period = CarbonPeriod::create($start, $end);
        $todayStr = Carbon::now()->toDateString();

        foreach ($period as $date) {
            $dateStr = $date->toDateString();
            $dayEvents = $events->get($dateStr, collect());

            $dayCheckIn = $dayEvents->first(fn($e) => in_array($e->attendanceStatus, ['checkIn', 'keldi', 'entered']));
            $dayCheckOut = $dayEvents->last(fn($e) => in_array($e->attendanceStatus, ['checkOut', 'ketdi', 'exited']));

            $isHoliday = isset($branchHolidays[$dateStr]);
            $isWeekend = in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);

            $dayStatus = 'absent';
            $lateMin = 0;
            $workedMin = 0;

            if ($dayCheckIn) {
                $totalDaysPresent++;
                $inTime = Carbon::parse($dayCheckIn->created_at);
                $scheduleStart = $worker->work_time ? Carbon::parse($dateStr . ' ' . $worker->work_time) : null;

                if ($scheduleStart && $inTime->greaterThan($scheduleStart)) {
                    $dayStatus = 'late';
                    $lateMin = (int) $inTime->diffInMinutes($scheduleStart);
                    $totalLateMinutes += $lateMin;
                } else {
                    $dayStatus = 'on_time';
                }

                if ($dayCheckOut) {
                    $outTime = Carbon::parse($dayCheckOut->created_at);
                    $workedMin = max(0, (int) $outTime->diffInMinutes($inTime));
                } else {
                    $workedMin = $dateStr === $todayStr ? max(0, (int) Carbon::now()->diffInMinutes($inTime)) : 0;
                }
                $totalWorkedMinutes += $workedMin;
            } elseif ($isHoliday) {
                $dayStatus = 'holiday';
            } elseif ($isWeekend) {
                $dayStatus = 'weekend';
            } elseif ($dateStr > $todayStr) {
                $dayStatus = 'upcoming';
            }

            $days[] = [
                'date' => $dateStr,
                'day' => $date->day,
                'day_name' => $date->locale('uz')->isoFormat('ddd'),
                'status' => $dayStatus,
                'check_in' => $dayCheckIn ? Carbon::parse($dayCheckIn->created_at)->format('H:i') : null,
                'check_out' => $dayCheckOut ? Carbon::parse($dayCheckOut->created_at)->format('H:i') : null,
                'worked_minutes' => $workedMin,
                'worked_hours' => round($workedMin / 60, 2),
                'late_minutes' => $lateMin,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $monthStr,
                'days' => $days,
                'summary' => [
                    'days_present' => $totalDaysPresent,
                    'total_worked_hours' => round($totalWorkedMinutes / 60, 2),
                    'total_late_minutes' => $totalLateMinutes,
                ]
            ]
        ]);
    }

    /**
     * Salary, advances, and running balance history
     */
    public function mySalary(Request $request): JsonResponse
    {
        $worker = $this->getWorker($request);
        if (!$worker) {
            return response()->json(['success' => false, 'message' => 'Xodim topilmadi.'], 404);
        }

        // Subquery 1: Accrued Salaries
        $salaries = DB::table('salaries as s')
            ->join('users as u', 's.user_id', '=', 'u.id')
            ->where('s.worker_id', $worker->id)
            ->select([
                's.id',
                's.amount',
                's.comment',
                'u.name as author_name',
                's.created_at',
                's.worker_id',
                DB::raw("'salary' as type"),
            ]);

        // Subquery 2: Salary Payments / Advances
        $payments = DB::table('salary_payments as sp')
            ->join('users as u', 'sp.user_id', '=', 'u.id')
            ->where('sp.worker_id', $worker->id)
            ->select([
                'sp.id',
                DB::raw('-1 * sp.amount as amount'),
                'sp.comment',
                'u.name as author_name',
                'sp.created_at',
                'sp.worker_id',
                DB::raw("'payment' as type"),
            ]);

        $historyQuery = $salaries->unionAll($payments);

        $history = DB::table(DB::raw("({$historyQuery->toSql()}) as h"))
            ->mergeBindings($historyQuery)
            ->selectRaw('
                h.*,
                ROUND(SUM(h.amount) OVER (ORDER BY h.created_at), 2) as balance
            ')
            ->orderByDesc('h.created_at')
            ->limit(50)
            ->get();

        $totalAccrued = (float) Salary::where('worker_id', $worker->id)->sum('amount');
        $totalPaid = (float) SalaryPayment::where('worker_id', $worker->id)->sum('amount');
        $currentBalance = round($totalAccrued - $totalPaid, 2);

        return response()->json([
            'success' => true,
            'data' => [
                'hour_price' => (float) $worker->hour_price,
                'fine_price' => (float) $worker->fine_price,
                'total_accrued' => $totalAccrued,
                'total_paid' => $totalPaid,
                'current_balance' => $currentBalance,
                'history' => $history,
            ]
        ]);
    }

    /**
     * Submit an employee request (Day off, advance, or explanation)
     */
    public function submitRequest(Request $request): JsonResponse
    {
        $worker = $this->getWorker($request);
        if (!$worker) {
            return response()->json(['success' => false, 'message' => 'Xodim topilmadi.'], 404);
        }

        $validated = $request->validate([
            'type' => 'required|string|in:day_off,advance,excuse',
            'date' => 'nullable|date',
            'amount' => 'nullable|numeric|min:0',
            'comment' => 'required|string|max:500',
        ]);

        // If day_off, optionally record in WorkerHoliday
        if ($validated['type'] === 'day_off' && !empty($validated['date'])) {
            WorkerHoliday::create([
                'worker_id' => $worker->id,
                'from' => $validated['date'],
                'to' => $validated['date'],
                'comment' => 'Xodim arizasi: ' . $validated['comment'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Arizangiz qabul qilindi va rahbariyatga yuborildi.',
            'data' => $validated,
        ]);
    }
}
