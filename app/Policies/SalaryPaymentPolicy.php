<?php

namespace App\Policies;

use App\Models\Salary\SalaryPayment;
use App\Models\User\User;

class SalaryPaymentPolicy
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

    public function view(User $user, SalaryPayment $salaryPayment): bool
    {
        return $user->hasWorkerAccess($salaryPayment->worker_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SalaryPayment $salaryPayment): bool
    {
        return $user->hasWorkerAccess($salaryPayment->worker_id);
    }

    public function delete(User $user, SalaryPayment $salaryPayment): bool
    {
        return $user->hasWorkerAccess($salaryPayment->worker_id);
    }
}
