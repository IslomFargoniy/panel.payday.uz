<?php

namespace App\Policies;

use App\Models\Branch\BranchDay;
use App\Models\User\User;

class BranchDayPolicy
{
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

    public function view(User $user, BranchDay $branchDay): bool
    {
        return $user->hasBranchAccess($branchDay->branch_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, BranchDay $branchDay): bool
    {
        return $user->hasBranchAccess($branchDay->branch_id);
    }

    public function delete(User $user, BranchDay $branchDay): bool
    {
        return $user->hasBranchAccess($branchDay->branch_id);
    }
}
