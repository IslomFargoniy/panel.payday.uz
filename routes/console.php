<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// deploy.sh deploy davomida storage/framework/deploying faylini yaratadi: shu vaqtda scheduler vazifalari o'tkazib yuboriladi.
// Fayl 15 daqiqadan eski bo'lsa (deploy yarim yo'lda to'xtagan) e'tiborga olinmaydi.
$deploying = fn () => is_file($flag = storage_path('framework/deploying')) && filemtime($flag) > time() - 900;

Schedule::command('hikvision:sync-events')->everyMinute()->runInBackground()->skip($deploying);
Schedule::command('hikvision:healthcheck')->everyMinute()->runInBackground()->skip($deploying);
// Kichik bo'shliqlar va kechikib yozilgan eventlarni tuzatish uchun har kecha oxirgi 3 kunni qayta sinxronlash
Schedule::command('hikvision:sync-events --days=3')->dailyAt('03:30')->runInBackground()->skip($deploying);
