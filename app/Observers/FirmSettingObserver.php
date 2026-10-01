<?php

namespace App\Observers;

use App\Models\Firm\FirmSetting;
use Illuminate\Support\Facades\Auth;

class FirmSettingObserver
{
    /**
     * Handle the FirmSetting "created" event.
     */
    public function creating(FirmSetting $firmSetting): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $firmSetting->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            }
        }
    }

    /**
     * Handle the FirmSetting "updated" event.
     */
    public function updating(FirmSetting $firmSetting): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $firmSetting->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            }
        }
    }

    /**
     * Handle the FirmSetting "deleted" event.
     */
    public function deleted(FirmSetting $firmSetting): void
    {
        //
    }

    /**
     * Handle the FirmSetting "restored" event.
     */
    public function restored(FirmSetting $firmSetting): void
    {
        //
    }

    /**
     * Handle the FirmSetting "force deleted" event.
     */
    public function forceDeleted(FirmSetting $firmSetting): void
    {
        //
    }
}
