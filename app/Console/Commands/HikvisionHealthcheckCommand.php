<?php

namespace App\Console\Commands;

use App\Models\Branch\BranchDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HikvisionHealthcheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hikvision:healthcheck {--restart : Force restart the daemon via Supervisor}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform health check and watchdog monitoring on Hikvision ISUP Gateway C++ Daemon';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $gatewayBaseUrl = rtrim(config('hikvision.gateway_url', 'http://127.0.0.1:7661'), '/');
        $healthUrl = $gatewayBaseUrl . '/health';
        $devicesUrl = $gatewayBaseUrl . '/api/devices';
        $cacheKeyFailures = 'hikvision_gateway_consecutive_failures';

        if ($this->option('restart')) {
            $this->warn('Force restarting Hikvision Gateway via Supervisor...');
            $this->restartGateway();
            return 0;
        }

        $this->line("Checking Hikvision Gateway health at: {$healthUrl}...");

        $isHealthy = false;
        $connectedCount = 0;
        $errorMessage = null;

        try {
            $response = Http::timeout(4)->get($healthUrl);
            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? null) === 'ok') {
                    $isHealthy = true;
                    $connectedCount = (int)($data['connected_devices_count'] ?? 0);
                }
            } else {
                $errorMessage = "HTTP Status: " . $response->status();
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        if ($isHealthy) {
            $previousFailures = (int)Cache::get($cacheKeyFailures, 0);
            if ($previousFailures > 0) {
                Cache::forget($cacheKeyFailures);
                $msg = "✅ <b>[HIKVISION GATEWAY TIKLANDI]</b>\nISUP Gateway C++ daemon qayta tiklandi va normal ishlamoqda.\nUланган qurilmalar soni: {$connectedCount}";
                $this->info($msg);
                if (function_exists('telegramlog')) {
                    telegramlog($msg);
                }
            } else {
                $this->info("✔ Gateway is healthy. Connected devices: {$connectedCount}");
            }

            // Sync online status of active devices
            $this->checkDevicesStatus($devicesUrl);
            return 0;
        }

        // Handle Failure
        $failures = (int)Cache::increment($cacheKeyFailures);
        $alertMsg = "⚠️ <b>[HIKVISION GATEWAY XATOLIK]</b>\nISUP Gateway C++ daemon javob bermayapti!\nXatolik: {$errorMessage}\nKetma-ket uzilishlar soni: {$failures}";

        $this->error($alertMsg);
        Log::critical("Hikvision ISUP Gateway Healthcheck Failed ({$failures} consecutive): {$errorMessage}");

        // Only send telegram alert on 2nd consecutive failure to avoid transient network blips
        if ($failures === 2 && function_exists('telegramlog')) {
            telegramlog($alertMsg);
        }

        // Self-healing: if failed 3 or more times, attempt restart via Supervisor
        if ($failures >= 3) {
            $this->warn("Self-healing triggered: attempting supervisor restart...");
            $restartResult = $this->restartGateway();

            if (function_exists('telegramlog')) {
                telegramlog("🔄 <b>[HIKVISION GATEWAY RESTART]</b>\nSupervisor orqali qayta ishga tushirish buyrug'i yuborildi.\nNatija: " . ($restartResult ? "Muvaffaqiyatli" : "Xatolik yuz berdi"));
            }
        }

        return 1;
    }

    /**
     * Check individual devices status from gateway API
     */
    protected function checkDevicesStatus(string $devicesUrl): void
    {
        try {
            $res = Http::timeout(3)->get($devicesUrl);
            if (!$res->successful()) {
                return;
            }

            $gatewayDevices = $res->json();
            if (!is_array($gatewayDevices)) {
                return;
            }

            $onlineDeviceIds = [];
            foreach ($gatewayDevices as $d) {
                if (!empty($d['device_id']) && ($d['online'] ?? false)) {
                    $onlineDeviceIds[] = $d['device_id'];
                }
            }

            $this->line("Online device IDs in gateway: " . implode(', ', $onlineDeviceIds));

            // Sync database branch_devices with actual gateway state
            $isupDevices = BranchDevice::where('connection_type', 'isup')
                ->where('status', 1)
                ->whereNotNull('device_id')
                ->get();

            foreach ($isupDevices as $dev) {
                $isCurrentlyOnline = in_array($dev->device_id, $onlineDeviceIds);
                if ($isCurrentlyOnline) {
                    $wasOffline = !$dev->is_online;
                    $dev->is_online = true;
                    $dev->last_seen_at = now();
                    $dev->save();

                    if ($wasOffline) {
                        $this->line("Device status updated: {$dev->device_id} is now ONLINE 🟢");
                        Log::info("BranchDevice status changed to ONLINE", ['device_id' => $dev->device_id]);
                    }
                } else {
                    if ($dev->is_online) {
                        $dev->is_online = false;
                        $dev->save();
                        $this->line("Device status updated: {$dev->device_id} is now OFFLINE 🔴");
                        Log::info("BranchDevice status changed to OFFLINE", ['device_id' => $dev->device_id]);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::warning("Failed to fetch gateway device status: " . $e->getMessage());
        }
    }

    /**
     * Restart the C++ daemon using supervisorctl
     */
    protected function restartGateway(): bool
    {
        $output = [];
        $returnVar = 0;
        @exec('sudo supervisorctl restart hikvision-gateway 2>&1', $output, $returnVar);

        $outputText = implode("\n", $output);
        Log::info("Supervisor restart hikvision-gateway output: {$outputText} (code: {$returnVar})");

        return $returnVar === 0;
    }
}
