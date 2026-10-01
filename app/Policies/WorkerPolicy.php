<?php

namespace App\Policies;

use App\Models\User\User;
use App\Models\Worker\Worker;

class WorkerPolicy
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

    public function view(User $user, Worker $worker): bool
    {
        return $user->hasWorkerAccess($worker);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Worker $worker): bool
    {
        return $user->hasWorkerAccess($worker);
    }

    public function delete(User $user, Worker $worker): bool
    {
        return $user->hasWorkerAccess($worker);
    }

    public function restore(User $user, Worker $worker): bool
    {
        return $user->hasWorkerAccess($worker);
    }

    public function forceDelete(User $user, Worker $worker): bool
    {
        return $user->hasWorkerAccess($worker);
    }
}
