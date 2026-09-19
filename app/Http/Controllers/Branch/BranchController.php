<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch\Branch;
use App\Models\Day;
use App\Models\Firm\Firm;
use App\Models\Worker\Worker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function store(StoreBranchRequest $request)
    {
        try {
            $firm = Firm::findOrFail($request->firm_id);

            if ($firm->branch_limit <= $firm->branches()->count()) {
                throw ValidationException::withMessages([
                    'error' => __('Branch limit exceeded')
                ]);
            }

            Branch::create($request->validated());

            return redirect()->back()->with('success', __('Branch created successfully.'));
        } catch (ValidationException $e) {
            // bu Inertia uchun mos keladi
            throw $e;
        } catch (\Exception $e) {
            // boshqa xatoliklar (server xatolari)
            throw ValidationException::withMessages([
                'error' => $e->getMessage() ?: __('Something went wrong')
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Branch $branch)
    {
        // Non-admin users must be part of the firm
        if (!Auth::user()->hasRole('Admin')) {
            Auth::user()->user_firms()
                ->where('firm_id', $branch->firm_id)
                ->firstOrFail(); // Throws if unauthorized
        }

        $branch->load([
            'branch_days' => function ($query) {
                $query->with('day')
                ->orderBy('day_id', 'asc');
            },
            'workers',
            'branch_holidays',
            'branch_devices'
        ]);

        $worker = Worker::with([])
            ->selectRaw('workers.*, getBalance(workers.id) as balance')
            ->where('branch_id', $branch->id)
            ->where(function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%")
                    ->orWhere('address', 'like', "%{$request->search}%")
                    ->orWhere('comment', 'like', "%{$request->search}%");
            })
            ->paginate($request->per_page ?? 10);

        $days = Day::all();

        return Inertia::render('branch/show', [
            'worker' => $worker,
            'branch' => $branch,
            'days' => $days
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Branch $branch)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        try {
            $branch->update($request->validated());
            return back()->with('success', 'Branch updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch)
    {
        try {
            $branch->delete();
            return back()->with('success', 'Branch deleted successfully.');
        } catch (\Exception $e) {
            // Proper Inertia error response
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()],
            ]);
        }
    }
}
