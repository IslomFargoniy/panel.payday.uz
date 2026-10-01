<?php

namespace App\Observers;

use App\Models\Branch\BranchDevice;
use Illuminate\Support\Facades\Auth;

class BranchDeviceObserver
{
    /**
     * Handle the BranchDevice "created" event.
     */
    public function creating(BranchDevice $branchDevice): void
    {

        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $branchDevice->branch->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            }
        }
    }

    /**
     * Handle the BranchDevice "updated" event.
     */
    public function updated(BranchDevice $branchDevice): void
    {
        //
    }

    /**
     * Handle the BranchDevice "deleted" event.
     */
    public function deleting(BranchDevice $branchDevice): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $branchDevice->branch->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            }
        }
    }

    /**
     * Handle the BranchDevice "restored" event.
     */
    public function restored(BranchDevice $branchDevice): void
    {
        //
    }

    /**
     * Handle the BranchDevice "force deleted" event.
     */
    public function forceDeleted(BranchDevice $branchDevice): void
    {
        //
    }
}
