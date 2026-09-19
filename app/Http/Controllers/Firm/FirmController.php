<?php

namespace App\Http\Controllers\Firm;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFirmRequest;
use App\Http\Requests\UpdateFirmRequest;
use App\Models\Firm\Firm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FirmController extends Controller
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

        $firm = Firm::with([
            'firm_setting',
            'firm_holidays',
            'user_firms'
        ])
            ->withCount('workers');

        if ($request->search) {
            $firm->where(function ($query) use ($request) {
                $query->whereLike('name', "%$request->search%")
                    ->orWhereLike('address', "%$request->search%")
                    ->orWhereLike('comment', "%$request->search%");
            });
        }

        if (!Auth::user()->hasRole('Admin')) {
            $firm->whereHas('user_firms', function ($query) {
                $query->where('user_id', Auth::id());
            });
        }

        $firm = $firm->paginate($per_page);

        return Inertia::render('firm/index', [
            'firm' => $firm
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
    public function store(StoreFirmRequest $request)
    {
        try {
            Firm::create($request->validated());

            return back()->with('success', 'Firm created successfully.');
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
    public function show(Request $request, Firm $firm)
    {
        $firm->load([
            'firm_holidays',
            'firm_setting',
            'branches' => function ($query) use ($request) {
                $query->withCount('workers');
                if ($request->filled('search')) {
                    $query->where(function ($q) use ($request) {
                        $q->where('name', 'like', "%{$request->search}%")
                            ->orWhere('address', 'like', "%{$request->search}%");
                    });
                }
            }
        ]);
        // Non-admin users must be part of the firm
        if (!Auth::user()->hasRole('Admin')) {
            Auth::user()->user_firms()
                ->where('firm_id', $firm->id)
                ->firstOrFail(); // Throws if unauthorized
        }

        return Inertia::render('branch/index', [
            'firm' => $firm
        ]);
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Firm $firm)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFirmRequest $request, Firm $firm)
    {
        try {
            $firm->update($request->validated());
            return back()->with('success', 'Firm updated successfully.');
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
    public function destroy(Firm $firm)
    {
        try {
            $firm->delete();
            return back()->with('success', 'Firm deleted successfully.');
        } catch (\Exception $e) {
            // Proper Inertia error response
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()],
            ]);
        }
    }
}
