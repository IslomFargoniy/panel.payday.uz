<?php

namespace App\Observers;

use App\Models\Worker\WorkerHoliday;
use Illuminate\Support\Facades\Auth;

class WorkerHolidayObserver
{
    /**
     * Handle the WorkerHoliday "created" event.
     */
    public function creating(WorkerHoliday $workerHoliday): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $worker = $workerHoliday->worker ?? \App\Models\Worker\Worker::with('branch')->find($workerHoliday->worker_id);
                $firmId = $worker?->branch?->firm_id;
                if (!$firmId || !$user->hasFirmAccess($firmId)) {
                    throw new \Exception('Unauthorized access to firm.');
                }
            }

            if ($user instanceof \App\Models\User\User) {
                $workerHoliday->user_id = $user->id;
            }
        }
    }

    /**
     * Handle the WorkerHoliday "updated" event.
     */
    public function updated(WorkerHoliday $workerHoliday): void
    {
        //
    }

    /**
     * Handle the WorkerHoliday "deleted" event.
     */
    public function deleting(WorkerHoliday $workerHoliday): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $worker = $workerHoliday->worker ?? \App\Models\Worker\Worker::with('branch')->find($workerHoliday->worker_id);
                $firmId = $worker?->branch?->firm_id;
                if (!$firmId || !$user->hasFirmAccess($firmId)) {
                    throw new \Exception('Unauthorized access to firm.');
                }
            }
        }
    }

    /**
     * Handle the WorkerHoliday "restored" event.
     */
    public function restored(WorkerHoliday $workerHoliday): void
    {
        //
    }

    /**
     * Handle the WorkerHoliday "force deleted" event.
     */
    public function forceDeleted(WorkerHoliday $workerHoliday): void
    {
        //
    }
}
