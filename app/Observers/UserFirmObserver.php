<?php

namespace App\Observers;

use App\Models\User\UserFirm;
use Illuminate\Support\Facades\Auth;

class UserFirmObserver
{
    /**
     * Handle the UserFirm "creating" event.
     */
    public function creating(UserFirm $userFirm): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                throw new \Exception('You are not allowed to access this page');
            }
        }
    }

    /**
     * Handle the UserFirm "updated" event.
     */
    public function updated(UserFirm $userFirm): void
    {
        //
    }

    /**
     * Handle the UserFirm "deleting" event.
     */
    public function deleting(UserFirm $userFirm): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                throw new \Exception('You are not allowed to access this page');
            }
        }
    }

    /**
     * Handle the UserFirm "restored" event.
     */
    public function restored(UserFirm $userFirm): void
    {
        //
    }

    /**
     * Handle the UserFirm "force deleted" event.
     */
    public function forceDeleted(UserFirm $userFirm): void
    {
        //
    }
}
