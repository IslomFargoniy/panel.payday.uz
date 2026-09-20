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

        $inTimeCount = (clone $workerQuery)
            ->join('hikvision_access_events as hae', function ($join) {
                $join->on('hae.employeeNoString', '=', 'workers.employeeNoString')
                    ->whereRaw('CURDATE() = DATE(hae.created_at)')
                    ->where('hae.attendanceStatus', 'checkIn')
                    ->whereRaw('TIME(hae.created_at) <= TIME(workers.work_time)');
            })
            ->distinct('workers.id')
            ->count('workers.id');

        $lateCount = (clone $workerQuery)
            ->join('hikvision_access_events as hae', function ($join) {
                $join->on('hae.employeeNoString', '=', 'workers.employeeNoString')
                    ->whereRaw('CURDATE() = DATE(hae.created_at)')
                    ->where('hae.attendanceStatus', 'checkIn')
                    ->whereRaw('TIME(hae.created_at) > TIME(workers.work_time)');
            })
            ->distinct('workers.id')
            ->count('workers.id');

        $cameCount = (clone $workerQuery)
            ->join('hikvision_access_events as hae', function ($join) {
                $join->on('hae.employeeNoString', '=', 'workers.employeeNoString')
                    ->whereRaw('CURDATE() = DATE(hae.created_at)')
                    ->where('hae.attendanceStatus', 'checkIn');
            })
            ->distinct('workers.id')
            ->count('workers.id');

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
