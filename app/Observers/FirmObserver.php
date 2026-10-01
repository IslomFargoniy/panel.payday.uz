<?php

namespace App\Observers;

use App\Models\Firm\Firm;
use Illuminate\Support\Facades\Auth;

class FirmObserver
{
    /**
     * Handle the Firm "created" event.
     */
    public function creating(Firm $firm): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                throw new \Exception('You are not allowed to access this page');
            }
        }
    }

    /**
     * Handle the Firm "updated" event.
     */
    public function updating(Firm $firm): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                throw new \Exception('You are not allowed to access this page');
            }
        }
    }

    /**
     * Handle the Firm "deleted" event.
     */
    public function deleting(Firm $firm): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                throw new \Exception('You are not allowed to access this page');
            }
        }

        if ($firm->firm_holidays()->count() > 0) {
            throw new \Exception('Firm has holidays associated with it.');
        }

        $firm->user_firms()->delete();
    }

    /**
     * Handle the Firm "restored" event.
     */
    public function restored(Firm $firm): void
    {
        //
    }

    /**
     * Handle the Firm "force deleted" event.
     */
    public function forceDeleted(Firm $firm): void
    {
        //
    }
}
