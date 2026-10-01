<?php

namespace App\Policies;

use App\Models\Branch\Branch;
use App\Models\User\User;

class BranchPolicy
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

    public function view(User $user, Branch $branch): bool
    {
        return $user->hasBranchAccess($branch);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->hasBranchAccess($branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->hasBranchAccess($branch);
    }

    public function restore(User $user, Branch $branch): bool
    {
        return $user->hasBranchAccess($branch);
    }

    public function forceDelete(User $user, Branch $branch): bool
    {
        return $user->hasBranchAccess($branch);
    }
}
