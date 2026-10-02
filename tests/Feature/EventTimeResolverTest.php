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

// ---- 5.5: backfill xavfsizligi (maosh himoyasi, CSV zaxira, rollback) ----

function backfillFixture(): array
{
    $firm = \App\Models\Firm\Firm::create([
        'name' => 'Backfill Firm', 'status' => 1, 'branch_limit' => 5, 'branch_price' => 0,
        'valid_date' => now()->addYear()->toDateString(),
    ]);
    $branch = \App\Models\Branch\Branch::create(['name' => 'Balam 1', 'firm_id' => $firm->id, 'work_time' => '09:00', 'end_time' => '18:00']);
    \App\Models\Branch\BranchDevice::create([
        'branch_id' => $branch->id, 'name' => 'ISUP', 'mac_address' => '00:11:22:33:44:99',
        'device_id' => 'branch14', 'connection_type' => 'isup', 'status' => 1,
    ]);
    $worker = \App\Models\Worker\Worker::factory()->create(['branch_id' => $branch->id]);
    $user = \App\Models\User\User::factory()->create();

    return [$branch, $worker->fresh(), $user];
}

function backfillEvent(string $emp, string $deviceTime, string $createdAt): HikvisionAccessEvent
{
    $ha = HikvisionAccess::create(['shortSerialNumber' => 'branch14', 'dateTime' => $deviceTime]);
    $e = new HikvisionAccessEvent(['employeeNoString' => $emp, 'name' => 'W', 'attendanceStatus' => 'checkIn']);
    $e->hikvision_access_id = $ha->id;
    $e->created_at = $createdAt;
    $e->updated_at = $createdAt;
    $e->save();

    return $e;
}

function cleanupBackfillCsv(): void
{
    foreach (glob(storage_path('app/backfill/backfill-*.csv')) ?: [] as $f) {
        @unlink($f);
    }
}

test('backfill skips events in paid salary periods unless --include-paid is given', function () {
    [$branch, $worker, $user] = backfillFixture();
    \App\Models\Salary\Salary::create([
        'user_id' => $user->id, 'worker_id' => $worker->id, 'amount' => 1, 'worked_minute' => 1,
        'break_minute' => 0, 'hour_price' => 1, 'from' => '2026-09-01', 'to' => '2026-09-30',
    ]);

    $paid = backfillEvent($worker->employeeNoString, '2026-09-19 08:00:00', '2026-09-21 10:00:00');
    $free = backfillEvent($worker->employeeNoString, '2026-10-01 08:00:00', '2026-10-02 11:00:00');

    $this->artisan('hikvision:backfill-event-time', ['--force' => true])
        ->expectsOutputToContain("O'TKAZIB YUBORILDI")
        ->assertSuccessful();

    expect($paid->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-09-21 10:00:00');
    expect($free->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 08:00:00');

    $this->artisan('hikvision:backfill-event-time', ['--force' => true, '--include-paid' => true])->assertSuccessful();
    expect($paid->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-09-19 08:00:00');

    cleanupBackfillCsv();
});

test('backfill writes a CSV backup and --rollback restores the old created_at', function () {
    cleanupBackfillCsv();
    [$branch, $worker] = backfillFixture();
    $e = backfillEvent($worker->employeeNoString, '2026-10-01 07:58:10', '2026-10-02 11:29:32');

    $this->artisan('hikvision:backfill-event-time', ['--force' => true, '--device' => 'branch14'])->assertSuccessful();
    expect($e->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 07:58:10');

    $files = glob(storage_path('app/backfill/backfill-*.csv'));
    expect($files)->toHaveCount(1);
    expect(file_get_contents($files[0]))->toContain("{$e->id},\"2026-10-02 11:29:32\"");

    // --force'siz rollback o'zgartirmaydi
    $this->artisan('hikvision:backfill-event-time', ['--rollback' => $files[0]])->assertSuccessful();
    expect($e->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 07:58:10');

    $this->artisan('hikvision:backfill-event-time', ['--rollback' => $files[0], '--force' => true])->assertSuccessful();
    expect($e->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-02 11:29:32');

    cleanupBackfillCsv();
});

test('backfill --only-day-mismatch and --threshold filters; device-ahead events untouched', function () {
    [$branch, $worker] = backfillFixture();
    $sameDay = backfillEvent($worker->employeeNoString, '2026-10-01 09:00:00', '2026-10-01 09:20:00'); // 20 daqiqa, bir kun
    $nextDay = backfillEvent($worker->employeeNoString, '2026-10-01 23:00:00', '2026-10-02 08:00:00'); // kun o'zgaradi
    $ahead = backfillEvent($worker->employeeNoString, '2026-10-02 09:02:15', '2026-10-02 09:00:00');   // soat oldinda

    $this->artisan('hikvision:backfill-event-time', ['--force' => true, '--only-day-mismatch' => true])->assertSuccessful();

    expect($sameDay->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 09:20:00');
    expect($nextDay->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 23:00:00');
    expect($ahead->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-02 09:00:00');

    // Katta chegara: 20 daqiqalik kechikish tuzatilmaydi
    $this->artisan('hikvision:backfill-event-time', ['--force' => true, '--threshold' => 3600])->assertSuccessful();
    expect($sameDay->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 09:20:00');

    cleanupBackfillCsv();
});
