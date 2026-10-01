<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);

        $data = $this->dashboardService->getDashboardData($request);

        return Inertia::render('dashboard', [
            'stats' => $data['stats'],
            'daily_stats' => $data['resultsForHisobot'],
            'firms' => $data['firms'],
            'branches' => $data['branches'],
            'filters' => [
                'month' => $data['month'],
                'branch_id' => $request->branch_id,
                'firm_id' => $request->firm_id,
                'from' => $request->from,
                'to' => $request->to,
            ],
        ]);
    }
}
