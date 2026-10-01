<?php

namespace App\Services\Hikvision;

use App\Enums\AttendanceStatus;
use App\Models\Hikvision\HikvisionAccessEvent;
use Carbon\Carbon;

class AttendanceStatusResolver
{
    /**
     * Resolve attendance status (checkIn / checkOut) for an event.
     *
     * 1. If $rawStatus is provided and valid, normalize and return it.
     * 2. If $rawStatus is missing/empty:
     *    - Inspect previous events for the same employee ON THE SAME CALENDAR DAY (dateTime < eventTime).
     *    - If no previous event exists today, default to 'checkIn'.
     *    - If previous event today was 'checkIn', toggle to 'checkOut'; otherwise 'checkIn'.
     */
    public static function resolve(?string $rawStatus, string $employeeNoString, string $dateTimeStr): string
    {
        $normalized = AttendanceStatus::normalize($rawStatus);
        if (!empty($normalized)) {
            return $normalized;
        }

        $eventDate = Carbon::parse($dateTimeStr)->toDateString();
        $startOfDay = $eventDate . ' 00:00:00';

        $lastEventToday = HikvisionAccessEvent::where('employeeNoString', $employeeNoString)
            ->whereHas('hikvisionAccess', function ($query) use ($startOfDay, $dateTimeStr) {
                $query->where('dateTime', '>=', $startOfDay)
                    ->where('dateTime', '<', $dateTimeStr);
            })
            ->join('hikvision_accesses', 'hikvision_access_events.hikvision_access_id', '=', 'hikvision_accesses.id')
            ->orderBy('hikvision_accesses.dateTime', 'desc')
            ->select('hikvision_access_events.*')
            ->first();

        if (!$lastEventToday) {
            return AttendanceStatus::IN->value;
        }

        $lastStatus = AttendanceStatus::normalize($lastEventToday->attendanceStatus);

        return ($lastStatus === AttendanceStatus::IN->value)
            ? AttendanceStatus::OUT->value
            : AttendanceStatus::IN->value;
    }
}
