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
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReportController extends Controller
{
    protected \App\Services\Attendance\AttendanceReportService $reportService;

    public function __construct(\App\Services\Attendance\AttendanceReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Build the paired events query using window functions (LEAD, ROW_NUMBER).
     * Replaces expensive derived-table self-joins and correlated subqueries.
     */
    public function buildPairedEventsQuery(Request $request, string $from, string $to)
    {
        return app(\App\Services\Attendance\AttendancePairingService::class)->buildPairedEventsQuery($request, $from, $to);
    }

    public function salary_report(Request $request)
    {
        try {
            $data = $this->reportService->getSalaryReportData($request);
            return Inertia::render('salary_report/index', $data);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()],
            ]);
        }
    }

    public function countWorkingDays(Request $request): int
    {
        $workerId = $request->worker_id > 0 ? (int)$request->worker_id : null;
        $branchId = $request->branch_id > 0 ? (int)$request->branch_id : null;
        $from = Carbon::parse($request->from)->startOfDay();
        $to = Carbon::parse($request->to)->startOfDay();

        $scheduleService = new \App\Services\Attendance\WorkScheduleService();

        if ($workerId) {
            $worker = Worker::with(['branch'])->find($workerId);
            if ($worker) {
                return $scheduleService->countWorkingDays($worker, $from, $to);
            }
        }

        return $scheduleService->countWorkingDaysForBranch($branchId, $from, $to);
    }

    public function monthly_attendance(Request $request)
    {
        $data = $this->reportService->getMonthlyAttendanceData($request);
        return Inertia::render('monthly_report/index', $data);
    }

    private function batchCalculateWorkingDays(array $workers, string $fromStr, string $toStr): array
    {
        return (new \App\Services\Attendance\WorkScheduleService())->batchCalculateWorkingDays($workers, $fromStr, $toStr);
    }
}
