<?php

namespace App\Services\Hikvision;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class EventTimeResolver
{
    /**
     * Resolve the event time considering device clock drift and system configuration.
     *
     * @param CarbonInterface|string|null $deviceTime
     * @param CarbonInterface|string|null $serverTime
     * @param string|null $identifier
     * @param bool $legacyUseDeviceTime
     * @return Carbon
     */
    public function resolve(
        $deviceTime,
        $serverTime = null,
        ?string $identifier = null,
        bool $legacyUseDeviceTime = false
    ): Carbon {
        $server = $serverTime
            ? ($serverTime instanceof CarbonInterface ? Carbon::instance($serverTime)->timezone('Asia/Tashkent') : Carbon::parse($serverTime)->timezone('Asia/Tashkent'))
            : Carbon::now('Asia/Tashkent');

        if (!$deviceTime) {
            return $server;
        }

        try {
            $device = ($deviceTime instanceof CarbonInterface)
                ? Carbon::instance($deviceTime)->timezone('Asia/Tashkent')
                : Carbon::parse($deviceTime)->timezone('Asia/Tashkent');
        } catch (\Throwable $e) {
            Log::warning("EventTimeResolver: Unable to parse device time '{$deviceTime}' for device '{$identifier}'", [
                'error' => $e->getMessage(),
            ]);
            return $server;
        }

        // Difference in seconds: positive if device clock is ahead of server
        $diffSeconds = $device->timestamp - $server->timestamp;

        $deviceAhead = $diffSeconds > 60;
        $deviceTooOld = $diffSeconds < -(7 * 86400);

        if ($deviceAhead) {
            Log::warning("EventTimeResolver: Clock drift detected - device clock ahead", [
                'device' => $identifier,
                'device_time' => $device->toDateTimeString(),
                'server_time' => $server->toDateTimeString(),
                'drift_seconds' => $diffSeconds,
            ]);
        } elseif ($deviceTooOld) {
            Log::warning("EventTimeResolver: Clock drift detected - event time older than 7 days", [
                'device' => $identifier,
                'device_time' => $device->toDateTimeString(),
                'server_time' => $server->toDateTimeString(),
                'drift_seconds' => $diffSeconds,
            ]);
        }

        $useDeviceTime = (bool) config('hikvision.use_device_time', false);

        if (!$useDeviceTime) {
            // When false, keep legacy behavior:
            // Sync legacy behavior was device time; callback legacy behavior was server time.
            if ($legacyUseDeviceTime && !$deviceAhead && !$deviceTooOld) {
                return $device;
            }

            // Kechikib yetkazilgan event (masalan internet uzilib, qurilma xotirasidan keyin yuborgan):
            // server vaqti noto'g'ri kunga yozib yuboradi, shuning uchun qurilma vaqti olinadi.
            $lateDeliverySeconds = (int) config('hikvision.late_delivery_seconds', 600);
            if ($lateDeliverySeconds > 0 && -$diffSeconds > $lateDeliverySeconds && !$deviceTooOld) {
                return $device;
            }

            return $server;
        }

        // When use_device_time is true:
        if ($deviceAhead || $deviceTooOld) {
            return $server;
        }

        return $device;
    }
}
