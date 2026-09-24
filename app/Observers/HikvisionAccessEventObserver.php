<?php

namespace App\Observers;

use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\User\User;
use App\Models\Worker\Worker;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\FileUpload\InputFile;

class HikvisionAccessEventObserver
{
    public function created(HikvisionAccessEvent $hikvisionAccessEvent): void
    {
        if (!$hikvisionAccessEvent->picture) {
            return;
        }

        $worker = Worker::query()
            ->where('employeeNoString', $hikvisionAccessEvent->employeeNoString)
            ->with('branch')
            ->first();

        if (!$worker || !$worker->branch) {
            return;
        }

        $users = User::query()
            ->whereNotNull('telegram_id')
            ->whereHas('user_firms', function ($q) use ($worker) {
            $q->where('firm_id', $worker->branch->firm_id);
        })
            ->get();

        $photoPath = Storage::disk('public')->path(
            'hikvision/' . $hikvisionAccessEvent->hikvisionAccess->shortSerialNumber . '/' . $hikvisionAccessEvent->picture
        );

        if (!file_exists($photoPath)) {
            return;
        }

        $hikvisionTime = Carbon::parse($hikvisionAccessEvent->hikvisionAccess->dateTime);

        // TIME larni shu kun bilan birlashtiramiz
        $workStart = $hikvisionAccessEvent->work_time
            ?Carbon::parse($hikvisionTime->format('Y-m-d') . ' ' . $hikvisionAccessEvent->work_time)
            : null;

        $workEnd = $hikvisionAccessEvent->end_time
            ?Carbon::parse($hikvisionTime->format('Y-m-d') . ' ' . $hikvisionAccessEvent->end_time)
            : null;

        $statusText = '';

        switch ($hikvisionAccessEvent->attendanceStatus) {

            case 'checkIn':

                if ($workStart) {

                    $diffMinutes = $workStart->diffInMinutes($hikvisionTime, false);

                    if ($diffMinutes <= 0) {
                        $statusText = "🚶 KELDI: 🟢 O‘z vaqtida";
                    }
                    else {
                        $hours = floor($diffMinutes / 60);
                        $minutes = round($diffMinutes % 60);
                        $statusText = "🚶 KELDI: 🔴 Kechikdi\n⏱️ {$hours} soat {$minutes} minut";
                    }
                }

                break;

            case 'checkOut':

                if ($workEnd) {

                    $diffMinutes = $workEnd->diffInMinutes($hikvisionTime, false);

                    if ($diffMinutes < 0) {
                        // Erta ketdi
                        $early = abs($diffMinutes);
                        $hours = floor($early / 60);
                        $minutes = round($early % 60);
                        $statusText = "🚶 KETDI: 🔴 Erta ketdi\n⏱️ {$hours} soat {$minutes} minut";

                    }
                    elseif ($diffMinutes > 0) {
                        // Ortiqcha ish
                        $hours = floor($diffMinutes / 60);
                        $minutes = round($diffMinutes % 60);
                        $statusText = "🚶 KETDI: 🟡 Ortiqcha ish\n⏱️ {$hours} soat {$minutes} minut";

                    }
                    else {
                        $statusText = "🚶 KETDI: 🟢 O‘z vaqtida";
                    }
                }

                break;

            default:
                $statusText = "ℹ️ Holat: {$hikvisionAccessEvent->attendanceStatus}";
                break;
        }

        $caption = "👤 Xodim: {$hikvisionAccessEvent->name}
--------------------------
{$statusText}
---------------------------
Filial: {$worker->branch->name}
Sana: " . $hikvisionTime->format('Y-m-d H:i:s');

        $chatIds = [];

        foreach ($users as $user) {
            if ($user->telegram_id) {
                $chatIds[] = $user->telegram_id;
            }
        }

        if ($worker->telegram_id) {
            $chatIds[] = $worker->telegram_id;
        }

        if ($worker->branch && $worker->branch->telegram_group_id) {
            $chatIds[] = $worker->branch->telegram_group_id;
        }

        if (!empty($chatIds)) {
            \App\Jobs\SendAttendanceTelegramNotificationJob::dispatch(
                $photoPath,
                $caption,
                $chatIds
            )->afterResponse();
        }
    }

    public function updated(HikvisionAccessEvent $hikvisionAccessEvent): void
    {
    }
    public function deleting(HikvisionAccessEvent $hikvisionAccessEvent): void
    {
    }
    public function restored(HikvisionAccessEvent $hikvisionAccessEvent): void
    {
    }
    public function forceDeleted(HikvisionAccessEvent $hikvisionAccessEvent): void
    {
    }
}