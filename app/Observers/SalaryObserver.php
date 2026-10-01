<?php

namespace App\Observers;

use App\Models\Salary\Salary;
use App\Models\Worker\Worker;
use Illuminate\Support\Facades\Auth;

class SalaryObserver
{
    /**
     * Handle the Salary "creating" event.
     */
    public function creating(Salary $salary): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $salary->worker->branch->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            }
        }

    }

    /**
     * Handle the Salary "updated" event.
     */
    public function updated(Salary $salary): void
    {
        //
    }

    /**
     * Handle the Salary "deleted" event.
     */
    public function deleting(Salary $salary): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms
                    ->where('firm_id', $salary->worker->branch->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            }
        }

        $balance = Worker::getWorkerBalance($salary->worker_id, true);

        if ($balance - $salary->getOriginal('amount') < 0) {
            throw new \Exception('Balans yetarli emas');
        }

    }

    /**
     * Handle the Salary "restored" event.
     */
    public function restored(Salary $salary): void
    {
        //
    }

    /**
     * Handle the Salary "force deleted" event.
     */
    public function forceDeleted(Salary $salary): void
    {
        //
    }
}
