<?php

namespace App\Observers;

use App\Models\Branch\BranchDay;
use Illuminate\Support\Facades\Auth;

class BranchDayObserver
{
    /**
     * Handle the BranchDay "created" event.
     */
    public function creating(BranchDay $branchDay): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $branchDay->branch->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            }
        }
    }

    /**
     * Handle the BranchDay "updated" event.
     */
    public function updated(BranchDay $branchDay): void
    {
        //
    }

    /**
     * Handle the BranchDay "deleted" event.
     */
    public function deleting(BranchDay $branchDay): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $branchDay->branch->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            }
        }
    }

    /**
     * Handle the BranchDay "restored" event.
     */
    public function restored(BranchDay $branchDay): void
    {
        //
    }

    /**
     * Handle the BranchDay "force deleted" event.
     */
    public function forceDeleted(BranchDay $branchDay): void
    {
        //
    }
}
