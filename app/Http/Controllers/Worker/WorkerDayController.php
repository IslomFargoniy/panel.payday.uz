<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkerDayRequest;
use App\Http\Requests\UpdateWorkerDayRequest;
use App\Models\Worker\WorkerDay;
use Illuminate\Validation\ValidationException;

class WorkerDayController extends Controller
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

    public function store(StoreWorkerDayRequest $request)
    {
        $validated = $request->validated();

        /** @var \App\Models\User\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && !$user->hasRole('Admin') && !$user->hasWorkerAccess($validated['worker_id'])) {
            abort(403, 'Unauthorized access to worker.');
        }

        try {
            $worker = WorkerDay::where([
                'worker_id' => $validated['worker_id']
            ])
                ->whereIn('day_id', $validated['day_ids'])
                ->first();

            if ($worker) {
                throw new \Exception('Already created.');
            }

            foreach ($validated['day_ids'] as $dayId) {
                WorkerDay::create([
                    'worker_id' => $validated['worker_id'],
                    'day_id' => $dayId,
                ]);
            }

            return back()->with('success', 'WorkerDay created successfully.');
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()],
            ]);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(WorkerDay $workerDay)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(WorkerDay $workerDay)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWorkerDayRequest $request, WorkerDay $workerDay)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WorkerDay $workerDay)
    {
        /** @var \App\Models\User\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && !$user->hasRole('Admin') && !$user->hasWorkerAccess($workerDay->worker_id)) {
            abort(403, 'Unauthorized access to worker.');
        }

        try {
            $workerDay->delete();
            return back()->with('success', 'WorkerDay deleted successfully.');
        } catch (\Exception $e) {
            // Proper Inertia error response
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()],
            ]);
        }
    }
}
