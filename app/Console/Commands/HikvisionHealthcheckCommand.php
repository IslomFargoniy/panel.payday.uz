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
    protected $signature = 'hikvision:healthcheck {--restart : Force restart the daemon}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform health check and watchdog monitoring on Hikvision ISUP Gateway C++ Daemon with exponential backoff';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $healthPath = '/health';
        $devicesPath = '/api/devices';

        $cacheKeyFailures = 'hikvision_gateway_consecutive_failures';
        $cacheKeyIsDown = 'hikvision_gateway_is_down';
        $cacheKeyLastRestart = 'hikvision_gateway_last_restart_at';
        $cacheKeyRestartCount = 'hikvision_gateway_restart_count';

        if ($this->option('restart')) {
            $this->warn('Force restarting Hikvision Gateway...');
            $this->restartGateway();
            return 0;
        }

        $this->line("Checking Hikvision Gateway health via GatewayClient...");

        $isHealthy = false;
        $connectedCount = 0;
        $errorMessage = null;

        try {
            $response = \App\Services\Hikvision\GatewayClient::http()->timeout(4)->get($healthPath);
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
            $wasDown = Cache::get($cacheKeyIsDown, false);
            if ($wasDown) {
                Cache::forget($cacheKeyIsDown);
                Cache::forget($cacheKeyFailures);
                Cache::forget($cacheKeyRestartCount);
                Cache::forget($cacheKeyLastRestart);

                $msg = "✅ <b>[HIKVISION GATEWAY TIKLANDI]</b>\nISUP Gateway C++ daemon qayta tiklandi va normal ishlamoqda.\nUланган qurilmalar soni: {$connectedCount}";
                $this->info($msg);
                if (function_exists('telegramlog')) {
                    telegramlog($msg);
                }
            } else {
                Cache::forget($cacheKeyFailures);
                $this->info("✔ Gateway is healthy. Connected devices: {$connectedCount}");
            }

            // Sync online status of active devices
            $this->checkDevicesStatus();
            return 0;
        }

        // Handle Failure
        $failures = (int)Cache::increment($cacheKeyFailures);
        $wasDown = Cache::get($cacheKeyIsDown, false);

        $this->error("ISUP Gateway failed ({$failures} consecutive): {$errorMessage}");
        Log::critical("Hikvision ISUP Gateway Healthcheck Failed ({$failures} consecutive): {$errorMessage}");

        // State Change: Alert only once when transitioning from UP to DOWN
        if (!$wasDown && $failures >= 2) {
            Cache::put($cacheKeyIsDown, true, now()->addDays(7));
            $alertMsg = "⚠️ <b>[HIKVISION GATEWAY XATOLIK]</b>\nISUP Gateway C++ daemon javob bermayapti!\nXatolik: {$errorMessage}\nKetma-ket uzilishlar soni: {$failures}";
            if (function_exists('telegramlog')) {
                telegramlog($alertMsg);
            }
        }

        // Self-healing with Backoff: [5, 15, 30] minutes between restarts
        if ($failures >= 3) {
            $backoffMinutes = [0 => 0, 1 => 5, 2 => 15, 3 => 30];
            $restartCount = (int)Cache::get($cacheKeyRestartCount, 0);
            $lastRestartAt = Cache::get($cacheKeyLastRestart);

            $requiredWaitMinutes = $backoffMinutes[min($restartCount, 3)] ?? 30;
            $canRestart = true;

            if ($lastRestartAt) {
                $minutesSinceLast = now()->diffInMinutes(\Carbon\Carbon::parse($lastRestartAt));
                if ($minutesSinceLast < $requiredWaitMinutes) {
                    $canRestart = false;
                    $remaining = $requiredWaitMinutes - $minutesSinceLast;
                    $this->warn("Restart backoff faol: navbatdagi qayta ishga tushirishga {$remaining} daqiqa qoldi.");
                }
            }

            if ($canRestart) {
                $this->warn("Self-healing triggered: attempting daemon restart (attempt #" . ($restartCount + 1) . ")...");
                $restartResult = $this->restartGateway();

                Cache::put($cacheKeyLastRestart, now()->toDateTimeString(), now()->addDays(1));
                Cache::increment($cacheKeyRestartCount);

                if (function_exists('telegramlog')) {
                    telegramlog("🔄 <b>[HIKVISION GATEWAY RESTART]</b>\nQayta ishga tushirish buyrug'i yuborildi.\nNatija: " . ($restartResult ? "Muvaffaqiyatli" : "Xatolik yuz berdi"));
                }
            }
        }

        return 1;
    }

    /**
     * Check individual devices status from gateway API
     */
    protected function checkDevicesStatus(): void
    {
        try {
            $res = \App\Services\Hikvision\GatewayClient::http()->timeout(3)->get('/api/devices');
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
     * Restart the C++ daemon using configured restart command
     */
    protected function restartGateway(): bool
    {
        $command = config('hikvision.restart_command', 'sudo systemctl restart hikvision-isup');
        $output = [];
        $returnVar = 0;
        @exec($command . ' 2>&1', $output, $returnVar);

        $outputText = implode("\n", $output);
        Log::info("Hikvision gateway restart output: {$outputText} (code: {$returnVar})");

        return $returnVar === 0;
    }
}
