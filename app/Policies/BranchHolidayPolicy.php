<?php

namespace App\Policies;

use App\Models\Branch\BranchHoliday;
use App\Models\User\User;

class BranchHolidayPolicy
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

    public function view(User $user, BranchHoliday $branchHoliday): bool
    {
        return $user->hasBranchAccess($branchHoliday->branch_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, BranchHoliday $branchHoliday): bool
    {
        return $user->hasBranchAccess($branchHoliday->branch_id);
    }

    public function delete(User $user, BranchHoliday $branchHoliday): bool
    {
        return $user->hasBranchAccess($branchHoliday->branch_id);
    }
}
