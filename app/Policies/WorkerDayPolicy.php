<?php

namespace App\Policies;

use App\Models\User\User;
use App\Models\Worker\WorkerDay;

class WorkerDayPolicy
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

    public function view(User $user, WorkerDay $workerDay): bool
    {
        return $user->hasWorkerAccess($workerDay->worker_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WorkerDay $workerDay): bool
    {
        return $user->hasWorkerAccess($workerDay->worker_id);
    }

    public function delete(User $user, WorkerDay $workerDay): bool
    {
        return $user->hasWorkerAccess($workerDay->worker_id);
    }
}
