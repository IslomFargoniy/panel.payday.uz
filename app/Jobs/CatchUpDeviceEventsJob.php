<?php

namespace App\Jobs;

use App\Models\Branch\BranchDevice;
use App\Services\Hikvision\HikvisionSyncService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ISUP qurilma uzoq vaqt aloqasiz qolib qayta ulanganda, offline davridagi eventlarni qurilma xotirasidan to'liq sinxronlaydi.
 */
class CatchUpDeviceEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(public int $deviceId, public string $offlineSince)
    {
    }

    /**
     * Oldingi last_seen_at bilan hozirgi vaqt orasida katta bo'shliq bo'lsa, catch-up job'ni navbatga qo'yadi.
     */
    public static function dispatchIfGap(BranchDevice $device, ?Carbon $previousSeen): bool
    {
        $gapMinutes = (int) config('hikvision.catchup_gap_minutes', 30);

        if ($gapMinutes <= 0 || !$previousSeen || $device->connection_type !== 'isup' || empty($device->device_id)) {
            return false;
        }

        if ($previousSeen->diffInMinutes(now()) < $gapMinutes) {
            return false;
        }

        // Bir vaqtning o'zida (healthcheck + callback) ikki marta ishga tushmasligi uchun
        if (!Cache::add("hikvision_catchup:{$device->id}", 1, now()->addMinutes(10))) {
            return false;
        }

        static::dispatch($device->id, $previousSeen->toDateTimeString());

        return true;
    }

    public function handle(HikvisionSyncService $syncService): void
    {
        $device = BranchDevice::with('branch')->find($this->deviceId);
        if (!$device) {
            return;
        }

        $tz = 'Asia/Tashkent';
        $since = Carbon::parse($this->offlineSince, $tz);
        $maxAgeDays = (int) config('hikvision.late_delivery_max_age_days', 45);
        $earliest = Carbon::now($tz)->subDays($maxAgeDays);
        $start = ($since->lt($earliest) ? $earliest : $since)->copy();

        $startTime = $start->format('Y-m-d\T00:00:00+05:00');
        $endTime = Carbon::now($tz)->addHours(1)->format('Y-m-d\T23:59:59+05:00');

        $res = $syncService->syncEventsFromDevice($device, $startTime, $endTime);

        $offlineFor = $since->diffForHumans(Carbon::now($tz), ['syntax' => Carbon::DIFF_ABSOLUTE, 'parts' => 2]);
        $branch = $device->branch?->name ?? ('#' . $device->branch_id);

        if ($res['success'] ?? false) {
            $synced = (int) ($res['synced_count'] ?? 0);
            $corrected = (int) ($res['corrected_count'] ?? 0);
            Log::info("Hikvision catch-up sync: {$device->device_id}", compact('synced', 'corrected') + ['from' => $startTime]);
            $msg = "🟢 <b>[QURILMA QAYTA ULANDI]</b>\n{$branch} ({$device->device_id})\nOffline bo'lgan: {$offlineFor}\nSinxron: {$synced} yangi, {$corrected} tuzatildi";
        } else {
            $err = $res['error'] ?? $res['message'] ?? 'Noma\'lum xato';
            Log::warning("Hikvision catch-up sync failed: {$device->device_id}: {$err}");
            $msg = "⚠️ <b>[QURILMA QAYTA ULANDI, SINXRON XATO]</b>\n{$branch} ({$device->device_id})\nOffline bo'lgan: {$offlineFor}\nXato: {$err}";
        }

        if (function_exists('telegramlog')) {
            telegramlog($msg);
        }
    }
}
