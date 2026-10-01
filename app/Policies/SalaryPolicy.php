<?php

namespace App\Policies;

use App\Models\Salary\Salary;
use App\Models\User\User;

class SalaryPolicy
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

    public function view(User $user, Salary $salary): bool
    {
        return $user->hasWorkerAccess($salary->worker_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Salary $salary): bool
    {
        return $user->hasWorkerAccess($salary->worker_id);
    }

    public function delete(User $user, Salary $salary): bool
    {
        return $user->hasWorkerAccess($salary->worker_id);
    }
}
