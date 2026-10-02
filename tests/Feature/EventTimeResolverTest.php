<?php

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDevice;
use App\Models\Firm\Firm;
use App\Models\Hikvision\HikvisionAccess;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\Worker\Worker;
use App\Services\Hikvision\EventTimeResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

test('EventTimeResolver returns server time and logs warning when device clock is ahead by more than 60s', function () {
    Log::shouldReceive('warning')->atLeast()->once();

    $resolver = new EventTimeResolver();
    $serverTime = Carbon::parse('2026-10-01 12:00:00', 'Asia/Tashkent');
    $deviceTime = Carbon::parse('2026-10-01 12:02:15', 'Asia/Tashkent'); // 135s ahead

    Config::set('hikvision.use_device_time', true);

    $resolved = $resolver->resolve($deviceTime, $serverTime, 'GF0132950');

    expect($resolved->toDateTimeString())->toBe('2026-10-01 12:00:00');
});

test('EventTimeResolver returns server time and logs warning when event is older than 7 days', function () {
    Log::shouldReceive('warning')->atLeast()->once();

    $resolver = new EventTimeResolver();
    $serverTime = Carbon::parse('2026-10-01 12:00:00', 'Asia/Tashkent');
    $deviceTime = Carbon::parse('2026-09-20 12:00:00', 'Asia/Tashkent'); // 11 days old

    Config::set('hikvision.use_device_time', true);

    $resolved = $resolver->resolve($deviceTime, $serverTime, 'AE7106709');

    expect($resolved->toDateTimeString())->toBe('2026-10-01 12:00:00');
});

test('EventTimeResolver uses device time when within valid range and use_device_time is true', function () {
    $resolver = new EventTimeResolver();
    $serverTime = Carbon::parse('2026-10-01 12:15:00', 'Asia/Tashkent');
    $deviceTime = Carbon::parse('2026-10-01 12:00:00', 'Asia/Tashkent'); // 15 mins late delivery

    Config::set('hikvision.use_device_time', true);

    $resolved = $resolver->resolve($deviceTime, $serverTime, 'AE7106709');

    expect($resolved->toDateTimeString())->toBe('2026-10-01 12:00:00');
});

test('EventTimeResolver preserves legacy behavior when use_device_time is false', function () {
    $resolver = new EventTimeResolver();
    $serverTime = Carbon::parse('2026-10-01 12:05:00', 'Asia/Tashkent');
    $deviceTime = Carbon::parse('2026-10-01 12:00:00', 'Asia/Tashkent'); // 5 daqiqa: late_delivery chegarasidan kichik

    Config::set('hikvision.use_device_time', false);

    // Callback default
    $resolvedCallback = $resolver->resolve($deviceTime, $serverTime, 'AE7106709', false);
    expect($resolvedCallback->toDateTimeString())->toBe('2026-10-01 12:05:00');

    // Sync legacy default
    $resolvedSync = $resolver->resolve($deviceTime, $serverTime, 'AE7106709', true);
    expect($resolvedSync->toDateTimeString())->toBe('2026-10-01 12:00:00');
});

test('EventTimeResolver uses device time for late-delivered callback events (offline backlog) when use_device_time is false', function () {
    $resolver = new EventTimeResolver();
    // Internet uzilgan: 1-oktabr 07:58 dagi event 2-oktabr 11:29 da yetib keldi
    $serverTime = Carbon::parse('2026-10-02 11:29:32', 'Asia/Tashkent');
    $deviceTime = Carbon::parse('2026-10-01 07:58:10', 'Asia/Tashkent');

    Config::set('hikvision.use_device_time', false);
    Config::set('hikvision.late_delivery_seconds', 600);

    expect($resolver->resolve($deviceTime, $serverTime, 'branch14')->toDateTimeString())->toBe('2026-10-01 07:58:10');

    // Chegaradan biroz kichik kechikish: server vaqti saqlanadi
    $nearServer = Carbon::parse('2026-10-01 08:08:00', 'Asia/Tashkent'); // 590s
    expect($resolver->resolve(Carbon::parse('2026-10-01 07:58:10', 'Asia/Tashkent'), $nearServer, 'branch14')->toDateTimeString())->toBe('2026-10-01 08:08:00');

    // 10 kunlik backlog (7 kundan eski, lekin 45 kun ichida): qurilma vaqti
    $tenDays = Carbon::parse('2026-09-22 08:00:00', 'Asia/Tashkent');
    expect($resolver->resolve($tenDays, $serverTime, 'branch14')->toDateTimeString())->toBe('2026-09-22 08:00:00');

    // 60 kunlik: server vaqti
    $tooOld = Carbon::parse('2026-08-03 08:00:00', 'Asia/Tashkent');
    expect($resolver->resolve($tooOld, $serverTime, 'branch14')->toDateTimeString())->toBe('2026-10-02 11:29:32');

    // Qoida o'chirilgan (0): eski xatti-harakat
    Config::set('hikvision.late_delivery_seconds', 0);
    expect($resolver->resolve($deviceTime, $serverTime, 'branch14')->toDateTimeString())->toBe('2026-10-02 11:29:32');
});

test('hikvision:drift-report command outputs analysis table', function () {
    $ha = HikvisionAccess::create([
        'macAddress' => '88:de:39:32:a9:dc',
        'shortSerialNumber' => 'GF0132950',
        'dateTime' => '2026-10-01 12:05:00',
    ]);

    $event = new HikvisionAccessEvent([
        'employeeNoString' => '1001',
        'name' => 'Test Worker',
        'attendanceStatus' => 'checkIn',
    ]);
    $event->hikvision_access_id = $ha->id;
    $event->created_at = Carbon::parse('2026-10-01 12:00:00');
    $event->updated_at = Carbon::parse('2026-10-01 12:00:00');
    $event->save();

    $this->artisan('hikvision:drift-report', ['--days' => 30])
        ->expectsOutputToContain('GF0132950')
        ->assertSuccessful();
});

test('hikvision:backfill-event-time dry-run and force execution', function () {
    // 1. Delayed event (created_at is 20 mins after dateTime)
    $haDelayed = HikvisionAccess::create([
        'shortSerialNumber' => 'AE7106709',
        'dateTime' => '2026-10-01 09:00:00',
    ]);
    $delayedEvent = new HikvisionAccessEvent([
        'employeeNoString' => '2001',
        'name' => 'Delayed Worker',
        'attendanceStatus' => 'checkIn',
    ]);
    $delayedEvent->hikvision_access_id = $haDelayed->id;
    $delayedEvent->created_at = Carbon::parse('2026-10-01 09:20:00'); // 1200s delay
    $delayedEvent->updated_at = Carbon::parse('2026-10-01 09:20:00');
    $delayedEvent->save();

    // 2. Negative drift event (device ahead, dateTime > created_at) - MUST NOT BE TOUCHED
    $haAhead = HikvisionAccess::create([
        'shortSerialNumber' => 'GF0132950',
        'dateTime' => '2026-10-01 09:02:15',
    ]);
    $aheadEvent = new HikvisionAccessEvent([
        'employeeNoString' => '2002',
        'name' => 'Ahead Worker',
        'attendanceStatus' => 'checkIn',
    ]);
    $aheadEvent->hikvision_access_id = $haAhead->id;
    $aheadEvent->created_at = Carbon::parse('2026-10-01 09:00:00');
    $aheadEvent->updated_at = Carbon::parse('2026-10-01 09:00:00');
    $aheadEvent->save();

    // Test Dry-run
    $this->artisan('hikvision:backfill-event-time', ['--dry-run' => true])
        ->expectsOutputToContain('AE7106709')
        ->expectsOutputToContain('DRY-RUN yakunlandi')
        ->assertSuccessful();

    // Verify dry run did not touch records
    expect($delayedEvent->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 09:20:00');

    // Test Force run
    $this->artisan('hikvision:backfill-event-time', ['--force' => true])
        ->expectsOutputToContain('Muvaffaqiyatli yakunlandi')
        ->assertSuccessful();

    // Verify delayed event was updated to device dateTime
    expect($delayedEvent->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 09:00:00');

    // Verify ahead event was NOT touched
    expect($aheadEvent->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 09:00:00');
});

test('hikvision:backfill-event-time filters by --from, --to and --device', function () {
    $make = function (string $serial, string $deviceTime, string $createdAt, string $emp) {
        $ha = HikvisionAccess::create(['shortSerialNumber' => $serial, 'dateTime' => $deviceTime]);
        $e = new HikvisionAccessEvent(['employeeNoString' => $emp, 'name' => 'W', 'attendanceStatus' => 'checkIn']);
        $e->hikvision_access_id = $ha->id;
        $e->created_at = Carbon::parse($createdAt);
        $e->updated_at = Carbon::parse($createdAt);
        $e->save();
        return $e;
    };

    $target = $make('branch14', '2026-10-01 07:58:10', '2026-10-02 11:29:32', '3001');
    $otherDevice = $make('GG8507033', '2026-10-01 08:00:00', '2026-10-02 11:00:00', '3002');
    $oldPeriod = $make('branch14', '2026-09-19 08:00:00', '2026-09-21 10:00:00', '3003');

    $this->artisan('hikvision:backfill-event-time', [
        '--force' => true,
        '--from' => '2026-10-01',
        '--to' => '2026-10-02',
        '--device' => 'branch14',
    ])->assertSuccessful();

    expect($target->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 07:58:10');
    expect($otherDevice->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-02 11:00:00');
    expect($oldPeriod->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-09-21 10:00:00');
});
