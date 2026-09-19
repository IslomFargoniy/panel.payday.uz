<?php

namespace App\Observers;

use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\Worker\Worker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class WorkerObserver
{
    /**
     * Handle the Worker "created" event.
     */
    public function creating(Worker $worker): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check() && !Auth::user()->hasRole('Admin')) {
            Auth::user()->user_firms()
                ->where('firm_id', $worker->branch->firm_id)
                ->firstOrFail(); // Throws if unauthorized
        }
    }

    public function created(Worker $worker): void
    {
        $worker->employeeNoString = (string)$worker->id;
        $worker->saveQuietly();

        try {
            app(\App\Services\Hikvision\HikvisionSyncService::class)->syncWorker($worker);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('WorkerObserver sync failed on create: ' . $e->getMessage());
        }
    }

    /**
     * Handle the Worker "updated" event.
     */
    public function updating(Worker $worker): void
    {
        // Non-admin users must be part of the firm
        if (Auth::check() && !Auth::user()->hasRole('Admin')) {
            Auth::user()->user_firms()
                ->where('firm_id', $worker->branch->firm_id)
                ->firstOrFail(); // Throws if unauthorized
        }
    }

    public function updated(Worker $worker): void
    {
        try {
            app(\App\Services\Hikvision\HikvisionSyncService::class)->syncWorker($worker);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('WorkerObserver sync failed on update: ' . $e->getMessage());
        }
    }

    /**
     * Handle the Worker "deleted" event.
     */
    public function deleting(Worker $worker): void
    {
        // 1️⃣ Authorization FIRST
        if (Auth::check() && !Auth::user()->hasRole('Admin')) {
            Auth::user()->user_firms()
                ->where('firm_id', $worker->branch->firm_id)
                ->firstOrFail();
        }

        // 2️⃣ Cascade delete all related records
        $employeeNo = (string)($worker->employeeNoString ?: $worker->id);

        // Delete HikvisionAccessEvents and related FaceRects & HikvisionAccess
        $events = HikvisionAccessEvent::where('employeeNoString', $employeeNo)->get();
        foreach ($events as $event) {
            $event->faceReact()->delete();
            $accessId = $event->hikvision_access_id;
            $event->delete();
            if ($accessId && !HikvisionAccessEvent::where('hikvision_access_id', $accessId)->exists()) {
                \App\Models\Hikvision\HikvisionAccess::where('id', $accessId)->delete();
            }
        }

        // Delete Salaries, Payments, Days, Holidays
        $worker->salaries()->delete();
        $worker->salary_payments()->delete();
        $worker->worker_holidays()->delete();
        $worker->worker_days()->delete();

        // Delete avatar file if exists
        if ($worker->avatar && \Illuminate\Support\Facades\Storage::disk('public')->exists($worker->avatar)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($worker->avatar);
        }
    }

    public function deleted(Worker $worker): void
    {
        try {
            app(\App\Services\Hikvision\HikvisionSyncService::class)->deleteWorker($worker);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('WorkerObserver delete sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle the Worker "restored" event.
     */
    public function restored(Worker $worker): void
    {
        //
    }

    /**
     * Handle the Worker "force deleted" event.
     */
    public function forceDeleted(Worker $worker): void
    {
        //
    }
}
