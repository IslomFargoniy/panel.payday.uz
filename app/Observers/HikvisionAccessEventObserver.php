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
        $this->handleNotification($hikvisionAccessEvent, 'created');
    }

    public function updated(HikvisionAccessEvent $hikvisionAccessEvent): void
    {
        if ($hikvisionAccessEvent->wasChanged('picture') && !empty($hikvisionAccessEvent->picture)) {
            $this->handleNotification($hikvisionAccessEvent, 'updated');
        }
    }

    protected function handleNotification(HikvisionAccessEvent $hikvisionAccessEvent, string $trigger = 'created'): void
    {
        if (empty($hikvisionAccessEvent->picture)) {
            \Illuminate\Support\Facades\Log::info("HikvisionAccessEventObserver [{$trigger}]: Event #{$hikvisionAccessEvent->id} has no picture, skipping notification.");
            return;
        }

        $worker = Worker::query()
            ->where('employeeNoString', $hikvisionAccessEvent->employeeNoString)
            ->with('branch')
            ->first();

        if (!$worker || !$worker->branch) {
            \Illuminate\Support\Facades\Log::info("HikvisionAccessEventObserver [{$trigger}]: Worker or branch not found for employeeNoString {$hikvisionAccessEvent->employeeNoString}, skipping.");
            return;
        }

        $hikvisionAccess = $hikvisionAccessEvent->hikvisionAccess;
        $shortSerial = $hikvisionAccess->shortSerialNumber ?? null;
        if (empty($shortSerial)) {
            \Illuminate\Support\Facades\Log::warning("HikvisionAccessEventObserver [{$trigger}]: shortSerialNumber is missing for event #{$hikvisionAccessEvent->id}.");
            return;
        }

        $photoPath = Storage::disk('public')->path(
            'hikvision/' . $shortSerial . '/' . $hikvisionAccessEvent->picture
        );

        if (!file_exists($photoPath)) {
            \Illuminate\Support\Facades\Log::warning("HikvisionAccessEventObserver [{$trigger}]: Photo file not found at {$photoPath}");
            return;
        }

        $dateTimeStr = $hikvisionAccess->dateTime ?? $hikvisionAccessEvent->created_at;
        $hikvisionTime = Carbon::parse($dateTimeStr);

        // TIME larni shu kun bilan birlashtiramiz
        $workStart = $hikvisionAccessEvent->work_time
            ? Carbon::parse($hikvisionTime->format('Y-m-d') . ' ' . $hikvisionAccessEvent->work_time)
            : null;

        $workEnd = $hikvisionAccessEvent->end_time
            ? Carbon::parse($hikvisionTime->format('Y-m-d') . ' ' . $hikvisionAccessEvent->end_time)
            : null;

        $statusText = '';

        switch ($hikvisionAccessEvent->attendanceStatus) {
            case 'checkIn':
                if ($workStart) {
                    $diffMinutes = (int) round($workStart->diffInMinutes($hikvisionTime, false));
                    if ($diffMinutes <= 0) {
                        $statusText = "🚶 KELDI: 🟢 O‘z vaqtida";
                    } else {
                        $hours = intdiv(abs($diffMinutes), 60);
                        $minutes = abs($diffMinutes) % 60;
                        $statusText = "🚶 KELDI: 🔴 Kechikdi\n⏱️ {$hours} soat {$minutes} minut";
                    }
                } else {
                    $statusText = "🚶 KELDI: 🟢 Keldi";
                }
                break;

            case 'checkOut':
                if ($workEnd) {
                    $diffMinutes = (int) round($workEnd->diffInMinutes($hikvisionTime, false));
                    if ($diffMinutes < 0) {
                        $early = abs($diffMinutes);
                        $hours = intdiv($early, 60);
                        $minutes = $early % 60;
                        $statusText = "🚶 KETDI: 🔴 Erta ketdi\n⏱️ {$hours} soat {$minutes} minut";
                    } elseif ($diffMinutes > 0) {
                        $hours = intdiv($diffMinutes, 60);
                        $minutes = $diffMinutes % 60;
                        $statusText = "🚶 KETDI: 🟡 Ortiqcha ish\n⏱️ {$hours} soat {$minutes} minut";
                    } else {
                        $statusText = "🚶 KETDI: 🟢 O‘z vaqtida";
                    }
                } else {
                    $statusText = "🚶 KETDI: 🟢 Ketdi";
                }
                break;

            default:
                $statusText = "ℹ️ Holat: {$hikvisionAccessEvent->attendanceStatus}";
                break;
        }

        $workerName = $hikvisionAccessEvent->name ?: ($worker->name ?: 'Noma\'lum');
        $branchName = $worker->branch->name ?? 'Noma\'lum';
        $formattedTime = $hikvisionTime->format('Y-m-d H:i:s');

        $caption = "👤 Xodim: {$workerName}\n"
            . "--------------------------\n"
            . "{$statusText}\n"
            . "---------------------------\n"
            . "Filial: {$branchName}\n"
            . "Sana: {$formattedTime}";

        $chatIds = [];

        $users = User::query()
            ->whereNotNull('telegram_id')
            ->whereHas('user_firms', function ($q) use ($worker) {
                $q->where('firm_id', $worker->branch->firm_id);
            })
            ->get();

        foreach ($users as $user) {
            if ($user->telegram_id) {
                $chatIds[] = $user->telegram_id;
            }
        }

        if ($worker->telegram_id) {
            $chatIds[] = $worker->telegram_id;
        }

        if ($worker->branch && !empty($worker->branch->telegram_group_id)) {
            $chatIds[] = $worker->branch->telegram_group_id;
        }

        $chatIds = array_values(array_unique(array_filter($chatIds)));

        if (!empty($chatIds)) {
            \Illuminate\Support\Facades\Log::info("HikvisionAccessEventObserver [{$trigger}]: Dispatching notification for event #{$hikvisionAccessEvent->id} to chatIds: " . implode(', ', $chatIds));
            // Dispatch directly to database queue
            \App\Jobs\SendAttendanceTelegramNotificationJob::dispatch(
                $photoPath,
                $caption,
                $chatIds
            );
        } else {
            \Illuminate\Support\Facades\Log::warning("HikvisionAccessEventObserver [{$trigger}]: No chat IDs found for event #{$hikvisionAccessEvent->id}.");
        }
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