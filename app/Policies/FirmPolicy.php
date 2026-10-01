<?php

namespace App\Policies;

use App\Models\Firm\Firm;
use App\Models\User\User;

class FirmPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Firm $firm): bool
    {
        return $user->hasFirmAccess($firm);
    }

    public function create(User $user): bool
    {
        return false; // Only Admin can create firms
    }

    public function update(User $user, Firm $firm): bool
    {
        return false; // Only Admin can edit firm settings/details
    }

    public function delete(User $user, Firm $firm): bool
    {
        return false; // Only Admin can delete firms
    }

    public function restore(User $user, Firm $firm): bool
    {
        return false;
    }

    public function forceDelete(User $user, Firm $firm): bool
    {
        return false;
    }
}
