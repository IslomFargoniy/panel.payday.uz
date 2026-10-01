<?php

namespace App\Observers;

use App\Models\User\User;
use App\Models\Worker\Worker;
use App\Models\Worker\WorkerDay;
use Illuminate\Support\Facades\Auth;

class WorkerDayObserver
{
    /**
     * Handle the WorkerDay "creating" event.
     */
    public function creating(WorkerDay $workerDay): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof User && !$user->hasRole('Admin')) {
                $worker = $workerDay->worker ?? Worker::with('branch')->find($workerDay->worker_id);
                if (!$worker || !$worker->branch || !$user->hasFirmAccess($worker->branch->firm_id)) {
                    throw new \Exception('You are not allowed to access this resource.');
                }
            }
        }
    }

    /**
     * Handle the WorkerDay "deleting" event.
     */
    public function deleting(WorkerDay $workerDay): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof User && !$user->hasRole('Admin')) {
                $worker = $workerDay->worker ?? Worker::with('branch')->find($workerDay->worker_id);
                if (!$worker || !$worker->branch || !$user->hasFirmAccess($worker->branch->firm_id)) {
                    throw new \Exception('You are not allowed to access this resource.');
                }
            }
        }
    }
}
