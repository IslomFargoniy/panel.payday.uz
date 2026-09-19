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
        if ($request->per_page) {
            $per_page = $request->per_page;
        } else {
            $per_page = 15;
        }

        $salary_payment = SalaryPayment::with([]);

        if ($request->search) {
            $salary_payment->whereHas('worker', function ($query) use ($request) {
                $query->whereLike('name', "%$request->search%")
                    ->orWhereLike('address', "%$request->search%")
                    ->orWhereLike('comment', "%$request->search%");
            });
        }

        if ($request->worker_id) {
            $salary_payment->where('worker_id', '=', $request->worker_id);
        }


        $firms = Firm::with([]);
        $branches = Branch::with([]);
        $workers = Worker::with([])
            ->select(
                'workers.*',
                DB::raw("getBalance(workers.id) as balance")
            );

        if (!Auth::user()->hasRole('Admin')) {
            $firms->whereHas('user_firms', function ($query) {
                $query->where('user_id', Auth::id());
            });

            $branches = $branches->whereHas('firm', function ($query) {
                $query->whereHas('user_firms', function ($query) {
                    $query->where('user_id', Auth::id());
                });
            });

            $workers = $workers->whereHas('branch', function ($query) {
                $query->whereHas('firm', function ($query) {
                    $query->whereHas('user_firms', function ($query) {
                        $query->where('user_id', Auth::id());
                    });
                });
            });

            $salary_payment = $salary_payment->whereHas('worker', function ($query) {
                $query->whereHas('branch', function ($query) {
                    $query->whereHas('firm', function ($query) {
                        $query->whereHas('user_firms', function ($query) {
                            $query->where('user_id', Auth::id());
                        });
                    });
                });
            });
        }

        $firms = $firms->get();
        $branches = $branches->get();
        $workers = $workers->get();

        $salary_payment = $salary_payment->paginate($per_page);

        return Inertia::render('salary_payment/index', [
            'salary_payment' => $salary_payment,
            'workers' => $workers,
            'firms' => $firms,
            'branches' => $branches,
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
