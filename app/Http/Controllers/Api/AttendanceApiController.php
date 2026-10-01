<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch\Branch;
use App\Services\Attendance\AttendanceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceApiController extends Controller
{
    protected AttendanceReportService $reportService;

    public function __construct(AttendanceReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Daily attendance for a specific branch (or all branches)
     */
    public function dailyAttendance(Request $request, ?int $branchId = null): JsonResponse
    {
        /** @var \App\Models\User\User $user */
        $user = $request->user();
        $branchQuery = Branch::with('firm');

        if (!$user->hasRole('Admin')) {
            $branchQuery->whereHas('firm.user_firms', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $branch = $branchId ? (clone $branchQuery)->find($branchId) : (clone $branchQuery)->first();
        if (!$branch) {
            return response()->json(['success' => false, 'message' => 'Filial topilmadi yoki ruxsat yo‘q.'], 404);
        }

        $data = $this->reportService->getDailyAttendanceData($request, $branch);

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
        $data = $this->reportService->getMonthlyAttendanceData($request);

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
        $data = $this->reportService->getAttendanceGridData($request);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
