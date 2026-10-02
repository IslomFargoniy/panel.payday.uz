<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('hikvision:sync-events')->everyMinute()->runInBackground();
Schedule::command('hikvision:healthcheck')->everyMinute()->runInBackground();
// Kichik bo'shliqlar va kechikib yozilgan eventlarni tuzatish uchun har kecha oxirgi 3 kunni qayta sinxronlash
Schedule::command('hikvision:sync-events --days=3')->dailyAt('03:30')->runInBackground();
