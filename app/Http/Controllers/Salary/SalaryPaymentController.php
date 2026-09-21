<?php

namespace App\Http\Controllers\Salary;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalaryPaymentRequest;
use App\Http\Requests\UpdateSalaryPaymentRequest;
use App\Models\Branch\Branch;
use App\Models\Firm\Firm;
use App\Models\Salary\SalaryPayment;
use App\Models\Worker\Worker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SalaryPaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $per_page = $request->per_page ? (int)$request->per_page : 15;

        $salary_payment = SalaryPayment::query()->with(['worker.branch.firm', 'user']);

        if ($request->search) {
            $salary_payment->whereHas('worker', function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%")
                    ->orWhere('address', 'like', "%{$request->search}%")
                    ->orWhere('comment', 'like', "%{$request->search}%");
            });
        }

        if ($request->worker_id) {
            $salary_payment->where('worker_id', '=', $request->worker_id);
        }

        if (!Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $salary_payment->whereHas('worker.branch', function ($query) use ($userFirms) {
                $query->whereIn('firm_id', $userFirms);
            });
        }

        $firms = Firm::query()->without(['branches'])->select('id', 'name');
        $branches = Branch::query()->without(['firm', 'workers'])->select('id', 'firm_id', 'name');
        $workers = Worker::query()->without(['branch'])->select('id', 'branch_id', 'name');

        if (!Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $firms->whereIn('id', $userFirms);
            $branches->whereIn('firm_id', $userFirms);
            $workers->whereHas('branch', function ($query) use ($userFirms) {
                $query->whereIn('firm_id', $userFirms);
            });
        }

        $salary_payment = $salary_payment->latest('id')->paginate($per_page);

        return Inertia::render('salary_payment/index', [
            'salary_payment' => $salary_payment,
            'workers' => $workers->get(),
            'firms' => $firms->get(),
            'branches' => $branches->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSalaryPaymentRequest $request)
    {
        try {
            $validated = $request->validated();
            $validated['user_id'] = $request->user()->id;
            SalaryPayment::create($validated);

            return back()->with('success', 'SalaryPayment created successfully.');
        } catch (\Exception $e) {
            // Proper Inertia error response
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(SalaryPayment $salaryPayment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SalaryPayment $salaryPayment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSalaryPaymentRequest $request, SalaryPayment $salaryPayment)
    {
        try {
            $salaryPayment->update($request->validated());
            return back()->with('success', 'Salary Payment updated successfully.');
        } catch (\Exception $e) {
            // Proper Inertia error response
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SalaryPayment $salaryPayment)
    {
        try {
            $salaryPayment->delete();
            return back()->with('success', 'SalaryPayment deleted successfully.');
        } catch (\Exception $e) {
            // Proper Inertia error response
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()],
            ]);
        }
    }
}
