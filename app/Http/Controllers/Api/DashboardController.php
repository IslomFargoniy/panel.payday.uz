<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);

        $data = $this->dashboardService->getDashboardData($request);

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_workers' => $data['stats']['all_worker'],
                    'in_time' => $data['stats']['on_time'],
                    'late' => $data['stats']['late'],
                    'not_come' => $data['stats']['absent'],
                    'on_holiday' => $data['stats']['on_holiday'],
                    'in_building' => $data['stats']['in_building'],
                    'gone' => $data['stats']['gone'],
                    'total_firms' => count($data['firms']),
                    'total_branches' => count($data['branches']),
                    'date' => $data['today'],
                    'month' => $data['month'],
                ],
                'chart_stats' => $data['resultsForHisobot'],
                'firms' => $data['firms'],
                'branches' => $data['branches'],
            ]
        ]);
    }
}
