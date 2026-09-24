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
    private function extractInertiaProps($response, Request $request): array
    {
        if ($response instanceof \Inertia\Response) {
            $req = clone $request;
            $req->headers->set('X-Inertia', 'true');
            $json = json_decode($response->toResponse($req)->getContent(), true);
            return $json['props'] ?? [];
        }
        return is_array($response) ? $response : [];
    }

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
        $response = $hikvisionController->daily_attendance($request, $branch);
        $data = $this->extractInertiaProps($response, $request);

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
        $data = $this->extractInertiaProps($response, $request);

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
        $data = $this->extractInertiaProps($response, $request);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
