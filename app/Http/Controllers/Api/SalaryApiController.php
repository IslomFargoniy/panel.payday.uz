<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportController;
use App\Models\Salary\Salary;
use App\Models\Salary\SalaryPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalaryApiController extends Controller
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

    public function salaryReport(Request $request): JsonResponse
    {
        $reportController = new ReportController();
        $response = $reportController->salary_report($request);
        $data = $this->extractInertiaProps($response, $request);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function salaryList(Request $request): JsonResponse
    {
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

        $perPage = $request->input('per_page', 15);
        $salaries = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $salaries,
        ]);
    }

    public function calculateSalary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'worker_id' => 'required|exists:workers,id',
            'amount' => 'required|numeric|min:0',
            'from' => 'required|date',
            'to' => 'required|date',
            'worked_minutes' => 'nullable|integer',
            'break_minutes' => 'nullable|integer',
            'hour_price' => 'nullable|numeric|min:0',
            'date' => 'nullable|date',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['date'] = $validated['date'] ?? date('Y-m-d');

        $salary = Salary::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Maosh muvaffaqiyatli hisoblandi va saqlandi.',
            'data' => $salary,
        ], 201);
    }

    public function paymentList(Request $request): JsonResponse
    {
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

        $perPage = $request->input('per_page', 15);
        $payments = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    public function storePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'worker_id' => 'required|exists:workers,id',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'comment' => 'nullable|string',
        ]);

        $validated['user_id'] = Auth::id();

        $payment = SalaryPayment::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'To‘lov muvaffaqiyatli saqlandi.',
            'data' => $payment,
        ], 201);
    }
}
