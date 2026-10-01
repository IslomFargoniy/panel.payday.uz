<?php

namespace App\Observers;

use App\Models\Salary\SalaryPayment;
use App\Models\Worker\Worker;
use Illuminate\Support\Facades\Auth;

class SalaryPaymentObserver
{
    /**
     * Handle the SalaryPayment "created" event.
     */
    public function creating(SalaryPayment $salaryPayment): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $allowed = $user->user_firms()
                    ->where('firm_id', $salaryPayment->worker->branch->firm_id)
                    ->exists();

                if (!$allowed) {
                    throw new \Exception('You do not belong to this firm.');
                }
            }
        }


        $balance = Worker::getWorkerBalance($salaryPayment->worker_id, true);

        if ($balance - $salaryPayment->amount < 0) {
            throw new \Exception('Balans yetarli emas');
        }

    }

    /**
     * Handle the SalaryPayment "updated" event.
     */
    public function updating(SalaryPayment $salaryPayment): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $allowed = $user->user_firms()
                    ->where('firm_id', $salaryPayment->worker->branch->firm_id)
                    ->exists();

                if (!$allowed) {
                    throw new \Exception('You do not belong to this firm.');
                }
            }
        }

        $balance = Worker::getWorkerBalance($salaryPayment->worker_id, true);

        if ($balance + $salaryPayment->getOriginal('amount') - $salaryPayment->amount < 0) {
            throw new \Exception('Balans yetarli emas');
        }
    }

    /**
     * Handle the SalaryPayment "deleted" event.
     */
    public function deleting(SalaryPayment $salaryPayment): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $allowed = $user->user_firms()
                    ->where('firm_id', $salaryPayment->worker->branch->firm_id)
                    ->exists();

                if (!$allowed) {
                    throw new \Exception('You do not belong to this firm.');
                }
            }
        }

    }

    /**
     * Handle the SalaryPayment "restored" event.
     */
    public function restored(SalaryPayment $salaryPayment): void
    {
        //
    }

    /**
     * Handle the SalaryPayment "force deleted" event.
     */
    public function forceDeleted(SalaryPayment $salaryPayment): void
    {
        //
    }
}
