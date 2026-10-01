<?php

namespace App\Observers;

use App\Jobs\SyncWorkerToHikvisionJob;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\Worker\Worker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WorkerObserver
{
    /**
     * Handle the Worker "creating" event.
     */
    public function creating(Worker $worker): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $worker->branch->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            }
        }
    }

    public function created(Worker $worker): void
    {
        $worker->employeeNoString = (string)$worker->id;
        $worker->saveQuietly();

        // Asynchronous queue dispatch to prevent blocking web requests
        SyncWorkerToHikvisionJob::dispatch($worker->id, 'sync');
    }

    /**
     * Handle the Worker "updating" event.
     */
    public function updating(Worker $worker): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $worker->branch->firm_id)
                    ->firstOrFail(); // Throws if unauthorized
            } elseif ($user instanceof Worker && $user->id !== $worker->id) {
                abort(403, 'Unauthorized worker update');
            }
        }
    }

    public function updated(Worker $worker): void
    {
        // Only dispatch Hikvision sync when relevant fields changed
        if ($worker->wasChanged(['name', 'avatar', 'status', 'employeeNoString', 'branch_id'])) {
            SyncWorkerToHikvisionJob::dispatch($worker->id, 'sync');
        }
    }

    /**
     * Handle the Worker "deleting" event.
     */
    public function deleting(Worker $worker): void
    {
        // 1️⃣ Authorization FIRST
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof \App\Models\User\User && !$user->hasRole('Admin')) {
                $user->user_firms()
                    ->where('firm_id', $worker->branch->firm_id)
                    ->firstOrFail();
            } elseif ($user instanceof Worker) {
                abort(403, 'Worker cannot delete worker accounts');
            }
        }

        // 2️⃣ Decision A4: Prevent deletion if worker balance != 0
        $balance = Worker::getWorkerBalance($worker->id, true);
        if (abs($balance) > 0.01) {
            throw ValidationException::withMessages([
                'error' => "Balansi 0 bo'lmagan xodimni o'chirib bo'lmaydi (Hozirgi balans: " . number_format($balance, 0, '', ' ') . " so'm).",
            ]);
        }

        // 3️⃣ If permanent deletion (forceDeleting): prevent if payment/salary history exists
        if ($worker->isForceDeleting()) {
            if ($worker->salaries()->exists() || $worker->salary_payments()->exists()) {
                throw ValidationException::withMessages([
                    'error' => "Maosh yoki to'lov tarixi mavjud bo'lgan xodimni butunlay o'chirib bo'lmaydi.",
                ]);
            }

            // Clean up custom schedule days & holidays on permanent wipe
            $worker->worker_holidays()->delete();
            $worker->worker_days()->delete();

            // Delete avatar file if exists
            if ($worker->avatar && Storage::disk('public')->exists($worker->avatar)) {
                Storage::disk('public')->delete($worker->avatar);
            }
        }
        // When soft-deleting: keep salaries, payments, events, and avatar intact!
    }

    public function deleted(Worker $worker): void
    {
        // Remove worker credentials/access from terminals upon deletion
        SyncWorkerToHikvisionJob::dispatch($worker->id, 'delete');
    }

    /**
     * Handle the Worker "restored" event.
     */
    public function restored(Worker $worker): void
    {
        // Re-sync worker to terminals upon restoration
        SyncWorkerToHikvisionJob::dispatch($worker->id, 'sync');
    }

    /**
     * Handle the Worker "force deleted" event.
     */
    public function forceDeleted(Worker $worker): void
    {
        //
    }
}
