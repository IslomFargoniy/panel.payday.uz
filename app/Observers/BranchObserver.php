<?php

namespace App\Observers;

use App\Models\Branch\Branch;
use Illuminate\Support\Facades\Auth;

class BranchObserver
{
    /**
     * Handle the Branch "created" event.
     */
    public function creating(Branch $branch): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $branch->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            }
        }
    }

    /**
     * Handle the Branch "updated" event.
     */
    public function updating(Branch $branch): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $branch->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            }
        }
    }

    /**
     * Handle the Branch "deleted" event.
     */
    public function deleting(Branch $branch): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $branch->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            }
        }
    }

    /**
     * Handle the Branch "restored" event.
     */
    public function restored(Branch $branch): void
    {
        //
    }

    /**
     * Handle the Branch "force deleted" event.
     */
    public function forceDeleted(Branch $branch): void
    {
        //
    }
}
