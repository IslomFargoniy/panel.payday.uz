<?php

namespace App\Jobs;

use App\Models\Worker\Worker;
use App\Services\Hikvision\HikvisionSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncWorkerToHikvisionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 15, 30];

    public int $workerId;
    public string $action;
    public ?array $workerSnapshot;

    /**
     * Create a new job instance.
     */
    public function __construct(int $workerId, string $action = 'sync', ?array $workerSnapshot = null)
    {
        $this->workerId = $workerId;
        $this->action = $action;
        $this->workerSnapshot = $workerSnapshot;
    }

    /**
     * Execute the job.
     */
    public function handle(HikvisionSyncService $syncService): void
    {
        try {
            $worker = Worker::withTrashed()->with(['branch.branch_devices'])->find($this->workerId);

            if ($this->action === 'delete') {
                if ($worker) {
                    $syncService->deleteWorker($worker);
                }
                return;
            }

            if ($worker && !$worker->trashed()) {
                $syncService->syncWorker($worker);
            }
        } catch (\Exception $e) {
            Log::warning("SyncWorkerToHikvisionJob failed for worker {$this->workerId}: " . $e->getMessage());
            throw $e; // Allow queue retries
        }
    }
}
