<?php

namespace App\Http\Controllers\Salary;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalaryRequest;
use App\Http\Requests\UpdateSalaryRequest;
use App\Models\Branch\Branch;
use App\Models\Branch\BranchDay;
use App\Models\Branch\BranchHoliday;
use App\Models\Firm\Firm;
use App\Models\Firm\FirmHoliday;
use App\Models\Salary\Salary;
use App\Models\Salary\SalaryBranchDay;
use App\Models\Salary\SalaryBranchHoliday;
use App\Models\Salary\SalaryFirmHoliday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SalaryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $per_page = $request->per_page ? (int)$request->per_page : 15;

        $salary = Salary::query()->with(['worker.branch.firm', 'user']);

        if ($request->search) {
            $salary->whereHas('worker', function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%")
                    ->orWhere('address', 'like', "%{$request->search}%")
                    ->orWhere('comment', 'like', "%{$request->search}%");
            });
        }

        if ($request->firm_id) {
            $salary->whereHas('worker.branch', function ($query) use ($request) {
                $query->where('firm_id', $request->firm_id);
            });
        }

        if ($request->branch_id) {
            $salary->whereHas('worker', function ($query) use ($request) {
                $query->where('branch_id', $request->branch_id);
            });
        }

        if (!Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $salary->whereHas('worker.branch', function ($query) use ($userFirms) {
                $query->whereIn('firm_id', $userFirms);
            });
        }

        $firms = Firm::query()->without(['branches'])->select('id', 'name');
        $branches = Branch::query()->without(['firm', 'workers'])->select('id', 'firm_id', 'name');

        if (!Auth::user()->hasRole('Admin')) {
            $userFirms = Auth::user()->user_firms()->pluck('firm_id');
            $firms->whereIn('id', $userFirms);
            $branches->whereIn('firm_id', $userFirms);
        }

        $salary = $salary->latest('id')->paginate($per_page);

        return Inertia::render('salary/index', [
            'salary' => $salary,
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
    public function store(StoreSalaryRequest $request)
    {
        try {

            DB::beginTransaction();
            $validated = $request->validated();
            $validated['user_id'] = $request->user()->id;
            $salary = Salary::create($validated);

            $firm_id = $salary->worker->branch->firm_id;

            $firm_holidays = FirmHoliday::select(
                'name',
                'date',
                'comment',
                DB::raw($salary->id . ' as salary_id')
            )
                ->where('firm_id', $firm_id)
                ->get();

// Convert to array and insert all at once
            SalaryFirmHoliday::insert($firm_holidays->toArray());

            //////

            $branch_id = $salary->worker->branch_id;

            $branch_holidays = BranchHoliday::select(
                'name',
                'date',
                'comment',
                DB::raw($salary->id . ' as salary_id')
            )
                ->where('branch_id', $branch_id)
                ->get();

// Convert to array and insert all at once
            SalaryBranchHoliday::insert($branch_holidays->toArray());

            //////

            $branch_id = $salary->worker->branch_id;

            $branch_days = BranchDay::select(
                'day_id',
                DB::raw($salary->id . ' as salary_id')
            )
                ->where('branch_id', $branch_id)
                ->get();

// Convert to array and insert all at once
            SalaryBranchDay::insert($branch_days->toArray());

            //////

            DB::commit();

            // Redirect back with Inertia-compatible flash message
            return back()->with('success', 'Salary created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            // Log to Telegram or other services
            telegramlog($e->getMessage());

            // Respond with a proper Inertia validation error (422)
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Salary $salary)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Salary $salary)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSalaryRequest $request, Salary $salary)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Salary $salary)
    {
        try {
            DB::beginTransaction();
            $salary->salary_firm_holidays()->delete();
            $salary->salary_branch_holidays()->delete();
            $salary->salary_branch_days()->delete();
            $salary->delete();

            DB::commit();

            // Redirect back with Inertia-compatible flash message
            return back()->with('success', 'Salary deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            // Log to Telegram or other services
            telegramlog($e->getMessage());

            // Respond with a proper Inertia validation error (422)
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()],
            ]);
        }
    }
}
