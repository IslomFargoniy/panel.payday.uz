<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch\Branch;
use App\Models\Firm\Firm;
use App\Models\Worker\Worker;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $month = $request->month ?: date('Y-m');
        $monthNumber = Carbon::parse($month)->month;
        $year = Carbon::parse($month)->year;

        $isAdmin = $user->hasRole('Admin');

        // Base worker query
        $workerQuery = Worker::query();
        if (!$isAdmin) {
            $workerQuery->whereHas('branch.firm.user_firms', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        if ($request->branch_id) {
            $workerQuery->where('branch_id', $request->branch_id);
        }
        if ($request->firm_id) {
            $workerQuery->whereHas('branch', function ($q) use ($request) {
                $q->where('firm_id', $request->firm_id);
            });
        }

        $allWorkerCount = (clone $workerQuery)->count('workers.id');

        // Today attendance counts
        $today = date('Y-m-d');
        $todayStart = Carbon::today()->startOfDay()->toDateTimeString();
        $todayEnd = Carbon::today()->endOfDay()->toDateTimeString();

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

        $attendanceResult = (clone $workerQuery)
            ->joinSub($todayFirstEvents, 'first_event', function ($join) {
                $join->on('workers.employeeNoString', '=', 'first_event.employeeNoString');
            })
            ->whereNotNull('first_event.first_created_at')
            ->selectRaw('
                COALESCE(SUM(CASE WHEN TIME(first_event.first_created_at) <= TIME(first_event.work_time) THEN 1 ELSE 0 END), 0) AS on_time,
                COALESCE(SUM(CASE WHEN TIME(first_event.first_created_at) > TIME(first_event.work_time) THEN 1 ELSE 0 END), 0) AS late
            ')
            ->first();

        $inTimeCount = (int) ($attendanceResult->on_time ?? 0);
        $lateCount = (int) ($attendanceResult->late ?? 0);
        $cameCount = $inTimeCount + $lateCount;
        $notComeCount = max(0, $allWorkerCount - $cameCount);

        // Firms and Branches for filter
        $firmsQuery = Firm::query();
        if (!$isAdmin) {
            $firmsQuery->whereHas('user_firms', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        $firms = $firmsQuery->get(['id', 'name']);

        $branchesQuery = Branch::query();
        if (!$isAdmin) {
            $branchesQuery->whereHas('firm.user_firms', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        $branches = $branchesQuery->get(['id', 'firm_id', 'name']);

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_workers' => $allWorkerCount,
                    'in_time' => $inTimeCount,
                    'late' => $lateCount,
                    'not_come' => $notComeCount,
                    'total_firms' => $firms->count(),
                    'total_branches' => $branches->count(),
                    'date' => $today,
                    'month' => $month,
                ],
                'firms' => $firms,
                'branches' => $branches,
            ]
        ]);
    }
}
