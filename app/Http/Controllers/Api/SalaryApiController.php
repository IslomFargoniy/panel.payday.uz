<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalaryPaymentRequest;
use App\Http\Requests\StoreSalaryRequest;
use App\Models\Salary\Salary;
use App\Models\Salary\SalaryPayment;
use App\Services\Attendance\AttendanceReportService;
use App\Services\Salary\SalaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalaryApiController extends Controller
{
    protected AttendanceReportService $reportService;
    protected SalaryService $salaryService;

    public function __construct(
        AttendanceReportService $reportService,
        SalaryService $salaryService
    ) {
        $this->reportService = $reportService;
        $this->salaryService = $salaryService;
    }

    public function salaryReport(Request $request): JsonResponse
    {
        $data = $this->reportService->getSalaryReportData($request);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function salaryList(Request $request): JsonResponse
    {
        /** @var \App\Models\User\User $user */
        $user = Auth::user();
        $query = Salary::with(['worker', 'user', 'worker.branch.firm']);

        if (!$user->hasRole('Admin')) {
            $query->whereHas('worker.branch.firm.user_firms', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($request->filled('worker_id')) {
            $query->where('worker_id', $request->worker_id);
        }

        $perPage = (int) $request->input('per_page', 15);
        $salaries = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $salaries,
        ]);
    }

    public function calculateSalary(StoreSalaryRequest $request): JsonResponse
    {
        /** @var \App\Models\User\User $user */
        $user = Auth::user();
        $validated = $request->validated();

        if (!$user->hasRole('Admin') && !$user->hasWorkerAccess($validated['worker_id'])) {
            return response()->json(['success' => false, 'message' => 'Ruxsat berilmagan.'], 403);
        }

        $salary = $this->salaryService->createSalary($validated, $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Maosh muvaffaqiyatli hisoblandi va saqlandi.',
            'data' => $salary,
        ], 201);
    }

    public function paymentList(Request $request): JsonResponse
    {
        /** @var \App\Models\User\User $user */
        $user = Auth::user();
        $query = SalaryPayment::with(['worker', 'user', 'worker.branch.firm']);

        if (!$user->hasRole('Admin')) {
            $query->whereHas('worker.branch.firm.user_firms', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($request->filled('worker_id')) {
            $query->where('worker_id', $request->worker_id);
        }

        $perPage = (int) $request->input('per_page', 15);
        $payments = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    public function storePayment(StoreSalaryPaymentRequest $request): JsonResponse
    {
        /** @var \App\Models\User\User $user */
        $user = Auth::user();
        $validated = $request->validated();

        if (!$user->hasRole('Admin') && !$user->hasWorkerAccess($validated['worker_id'])) {
            return response()->json(['success' => false, 'message' => 'Ruxsat berilmagan.'], 403);
        }

        $validated['user_id'] = $user->id;

        $payment = SalaryPayment::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'To‘lov muvaffaqiyatli saqlandi.',
            'data' => $payment,
        ], 201);
    }
}
