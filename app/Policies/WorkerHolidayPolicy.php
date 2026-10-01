<?php

namespace App\Policies;

use App\Models\User\User;
use App\Models\Worker\WorkerHoliday;

class WorkerHolidayPolicy
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

    public function view(User $user, WorkerHoliday $workerHoliday): bool
    {
        return $user->hasWorkerAccess($workerHoliday->worker_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WorkerHoliday $workerHoliday): bool
    {
        return $user->hasWorkerAccess($workerHoliday->worker_id);
    }

    public function delete(User $user, WorkerHoliday $workerHoliday): bool
    {
        return $user->hasWorkerAccess($workerHoliday->worker_id);
    }
}
