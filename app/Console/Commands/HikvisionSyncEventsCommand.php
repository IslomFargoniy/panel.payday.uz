<?php

namespace App\Console\Commands;

use App\Models\Branch\BranchDevice;
use App\Services\Hikvision\HikvisionSyncService;
use Illuminate\Console\Command;

class HikvisionSyncEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hikvision:sync-events {--device_id= : Specific ISUP device ID to sync} {--days=1 : Number of past days to query}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync attendance event logs from Hikvision ISUP devices';

    /**
     * Execute the console command.
     */
    public function handle(HikvisionSyncService $syncService): int
    {
        $deviceId = $this->option('device_id');
        $days = (int)($this->option('days') ?: 1);

        $startTime = now()->subDays($days)->format('Y-m-d\T00:00:00+05:00');
        $endTime = now()->addHours(1)->format('Y-m-d\T23:59:59+05:00');

        $query = BranchDevice::where('connection_type', 'isup')
            ->where('status', 1)
            ->whereNotNull('device_id');

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->info('No active ISUP devices found.');
            return 0;
        }

        $this->info("Starting ISUP event sync for {$devices->count()} device(s)...");

        foreach ($devices as $device) {
            $this->line("Syncing device: {$device->device_id} (Branch ID: {$device->branch_id})...");
            $res = $syncService->syncEventsFromDevice($device, $startTime, $endTime);

            if ($res['success'] ?? false) {
                $this->info("✔ Device {$device->device_id}: {$res['synced_count']} new event(s) synced.");
            } else {
                $this->error("✖ Device {$device->device_id} sync failed: " . ($res['error'] ?? $res['message'] ?? 'Unknown error'));
            }
        }

        $this->info('ISUP Event Sync completed.');
        return 0;
    }
}
