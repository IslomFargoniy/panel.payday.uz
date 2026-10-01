<?php

namespace App\Observers;

use App\Models\Branch\BranchHoliday;
use Illuminate\Support\Facades\Auth;

class BranchHolidayObserver
{
    /**
     * Handle the BranchHoliday "created" event.
     */
    public function creating(BranchHoliday $branchHoliday): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $branchHoliday->branch->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            }
        }
    }

    /**
     * Handle the BranchHoliday "updated" event.
     */
    public function updated(BranchHoliday $branchHoliday): void
    {
        //
    }

    /**
     * Handle the BranchHoliday "deleted" event.
     */
    public function deleting(BranchHoliday $branchHoliday): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $branchHoliday->branch->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            }
        }
    }

    /**
     * Handle the BranchHoliday "restored" event.
     */
    public function restored(BranchHoliday $branchHoliday): void
    {
        //
    }

    /**
     * Handle the BranchHoliday "force deleted" event.
     */
    public function forceDeleted(BranchHoliday $branchHoliday): void
    {
        //
    }
}
