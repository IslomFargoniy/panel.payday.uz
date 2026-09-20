<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Hikvision\HikvisionController;
use App\Http\Controllers\ReportController;
use App\Models\Branch\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceApiController extends Controller
{
    /**
     * Daily attendance for a specific branch (or all branches)
     */
    public function dailyAttendance(Request $request, ?int $branchId = null): JsonResponse
    {
        $branch = $branchId ? Branch::with('firm')->find($branchId) : Branch::with('firm')->first();
        if (!$branch) {
            return response()->json(['success' => false, 'message' => 'Filial topilmadi.'], 404);
        }

        $hikvisionController = new HikvisionController();
        $response = $hikvisionController->daily_attendance($branch, $request);

        // Get props from Inertia response if it's an InertiaResponse, or pass raw data
        $data = method_exists($response, 'toResponse') ? $response->getData() : (is_array($response) ? $response : []);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Monthly attendance report (aggregated stats per worker)
     */
    public function monthlyAttendance(Request $request): JsonResponse
    {
        $reportController = new ReportController();
        $response = $reportController->monthly_attendance($request);
        $data = method_exists($response, 'toResponse') ? $response->getData() : (is_array($response) ? $response : []);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Monthly grid attendance (matrix 1..31 days)
     */
    public function attendanceGrid(Request $request): JsonResponse
    {
        $hikvisionController = new HikvisionController();
        $response = $hikvisionController->attendance($request);
        $data = method_exists($response, 'toResponse') ? $response->getData() : (is_array($response) ? $response : []);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
