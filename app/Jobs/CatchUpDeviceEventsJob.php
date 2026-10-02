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

    // Database queue retry_after = 90s: timeout undan kichik bo'lishi shart, aks holda job ikkinchi worker tomonidan
    // parallel qayta olinadi. Uzun bo'shliq kunlik oynalarga bo'linadi va vaqt tugasa qolgani yangi job sifatida davom etadi.
    public int $timeout = 80;

    protected const WINDOW_DAYS = 2;
    protected const TIME_BUDGET_SECONDS = 50;

    public function __construct(
        public int $deviceId,
        public string $offlineSince,
        public ?string $resumeFrom = null,
        public int $syncedSoFar = 0,
        public int $correctedSoFar = 0,
    ) {
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
        $startedAt = microtime(true);
        $since = Carbon::parse($this->offlineSince, $tz);
        $maxAgeDays = (int) config('hikvision.late_delivery_max_age_days', 45);
        $earliest = Carbon::now($tz)->subDays($maxAgeDays)->startOfDay();

        $cursor = $this->resumeFrom
            ? Carbon::parse($this->resumeFrom, $tz)->startOfDay()
            : ($since->lt($earliest) ? $earliest : $since->copy()->startOfDay());
        $today = Carbon::now($tz)->startOfDay();

        $synced = $this->syncedSoFar;
        $corrected = $this->correctedSoFar;
        $error = null;

        while ($cursor->lte($today)) {
            $windowEnd = $cursor->copy()->addDays(self::WINDOW_DAYS - 1);
            $startTime = $cursor->format('Y-m-d\T00:00:00+05:00');
            $endTime = $windowEnd->gte($today)
                ? Carbon::now($tz)->addHours(1)->format('Y-m-d\T23:59:59+05:00')
                : $windowEnd->format('Y-m-d\T23:59:59+05:00');

            $res = $syncService->syncEventsFromDevice($device, $startTime, $endTime);
            if (!($res['success'] ?? false)) {
                $error = $res['error'] ?? $res['message'] ?? 'Noma\'lum xato';
                break;
            }
            $synced += (int) ($res['synced_count'] ?? 0);
            $corrected += (int) ($res['corrected_count'] ?? 0);

            $cursor = $windowEnd->copy()->addDay();

            // Vaqt byudjeti tugasa, qolgan oraliq yangi job sifatida davom etadi
            if ($cursor->lte($today) && (microtime(true) - $startedAt) > self::TIME_BUDGET_SECONDS) {
                static::dispatch($this->deviceId, $this->offlineSince, $cursor->toDateString(), $synced, $corrected);
                return;
            }
        }

        $offlineFor = $since->diffForHumans(Carbon::now($tz), ['syntax' => Carbon::DIFF_ABSOLUTE, 'parts' => 2]);
        $branch = $device->branch?->name ?? ('#' . $device->branch_id);

        if ($error === null) {
            Log::info("Hikvision catch-up sync: {$device->device_id}", compact('synced', 'corrected') + ['since' => $this->offlineSince]);
            $msg = "🟢 <b>[QURILMA QAYTA ULANDI]</b>\n{$branch} ({$device->device_id})\nOffline bo'lgan: {$offlineFor}\nSinxron: {$synced} yangi, {$corrected} tuzatildi";
        } else {
            Log::warning("Hikvision catch-up sync failed: {$device->device_id}: {$error}");
            $msg = "⚠️ <b>[QURILMA QAYTA ULANDI, SINXRON XATO]</b>\n{$branch} ({$device->device_id})\nOffline bo'lgan: {$offlineFor}\nXato: {$error}";
        }

        if (function_exists('telegramlog')) {
            telegramlog($msg);
        }
    }
}
