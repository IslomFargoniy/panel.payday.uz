<?php

namespace App\Policies;

use App\Models\Branch\BranchDevice;
use App\Models\User\User;

class BranchDevicePolicy
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

    public function view(User $user, BranchDevice $branchDevice): bool
    {
        return $user->hasBranchAccess($branchDevice->branch_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, BranchDevice $branchDevice): bool
    {
        return $user->hasBranchAccess($branchDevice->branch_id);
    }

    public function delete(User $user, BranchDevice $branchDevice): bool
    {
        return $user->hasBranchAccess($branchDevice->branch_id);
    }

    public function restore(User $user, BranchDevice $branchDevice): bool
    {
        return $user->hasBranchAccess($branchDevice->branch_id);
    }

    public function forceDelete(User $user, BranchDevice $branchDevice): bool
    {
        return $user->hasBranchAccess($branchDevice->branch_id);
    }
}
