<?php

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDevice;
use App\Models\Firm\Firm;
use App\Models\Worker\Worker;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    Config::set('hikvision.catchup_gap_minutes', 30);
    Carbon::setTestNow(Carbon::parse('2026-10-02 11:29:32', 'Asia/Tashkent'));

    $firm = Firm::create([
        'name' => 'CatchUp Firm',
        'status' => 1,
        'branch_limit' => 5,
        'branch_price' => 0,
        'valid_date' => now()->addYear()->toDateString(),
    ]);
    $this->branch = Branch::create(['name' => 'Balam 1', 'firm_id' => $firm->id, 'work_time' => '09:00', 'end_time' => '18:00']);
    Worker::factory()->create(['branch_id' => $this->branch->id]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeIsupDevice(int $branchId, bool $online, ?string $lastSeen): BranchDevice
{
    $d = BranchDevice::create([
        'branch_id' => $branchId,
        'name' => 'Balam ISUP',
        'mac_address' => '00:11:22:33:44:99',
        'device_id' => 'branch14',
        'connection_type' => 'isup',
        'status' => 1,
        'is_online' => $online,
    ]);
    $d->forceFill(['last_seen_at' => $lastSeen])->save();

    return $d;
}

function fakeGatewayOnline(): void
{
    Http::fake([
        '*/health' => Http::response(['status' => 'ok', 'connected_devices_count' => 1]),
        '*/api/devices' => Http::response([['device_id' => 'branch14', 'online' => true]]),
        '*/api/isapi' => Http::response(['response' => json_encode(['AcsEvent' => ['totalMatches' => 0, 'numOfMatches' => 0, 'InfoList' => []]])]),
    ]);
}

test('device reconnecting after a long gap triggers catch-up sync from the day it was last seen', function () {
    $device = makeIsupDevice($this->branch->id, false, '2026-10-01 18:00:00');
    fakeGatewayOnline();

    $this->artisan('hikvision:healthcheck')->assertSuccessful();

    Http::assertSent(function (Request $request) {
        if (!str_contains($request->url(), '/api/isapi')) {
            return false;
        }
        $cond = json_decode($request['body'], true)['AcsEventCond'] ?? [];

        return ($cond['startTime'] ?? null) === '2026-10-01T00:00:00+05:00';
    });

    expect($device->fresh()->is_online)->toBeTrue();
    expect($device->fresh()->last_seen_at->format('Y-m-d H:i:s'))->toBe('2026-10-02 11:29:32');
});

test('device seen recently does not trigger catch-up on repeated healthchecks', function () {
    makeIsupDevice($this->branch->id, true, '2026-10-02 11:28:40');
    fakeGatewayOnline();

    $this->artisan('hikvision:healthcheck')->assertSuccessful();
    $this->artisan('hikvision:healthcheck')->assertSuccessful();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/api/isapi'));
});

test('catch-up is queued only once for concurrent reconnect signals', function () {
    $device = makeIsupDevice($this->branch->id, false, '2026-10-01 18:00:00');
    fakeGatewayOnline();

    $this->artisan('hikvision:healthcheck')->assertSuccessful();
    // Ikkinchi signal (callback): last_seen_at allaqachon yangilangan, shuning uchun qayta ishga tushmaydi
    $device->fresh()->markSeen();

    Http::assertSentCount(3); // health + devices + bitta isapi
});

test('catch-up is disabled when gap setting is 0', function () {
    Config::set('hikvision.catchup_gap_minutes', 0);
    makeIsupDevice($this->branch->id, false, '2026-10-01 18:00:00');
    fakeGatewayOnline();

    $this->artisan('hikvision:healthcheck')->assertSuccessful();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/api/isapi'));
});

test('hikvision:sync-events accepts --from and --to', function () {
    $device = makeIsupDevice($this->branch->id, true, '2026-10-02 11:29:00');
    fakeGatewayOnline();

    $this->artisan('hikvision:sync-events', ['--from' => '2026-09-28', '--to' => '2026-09-30'])->assertSuccessful();

    Http::assertSent(function (Request $request) {
        if (!str_contains($request->url(), '/api/isapi')) {
            return false;
        }
        $cond = json_decode($request['body'], true)['AcsEventCond'] ?? [];

        return $cond['startTime'] === '2026-09-28T00:00:00+05:00' && $cond['endTime'] === '2026-09-30T23:59:59+05:00';
    });
});

test('long offline device raises a one-time alert that is cleared on reconnect', function () {
    $device = makeIsupDevice($this->branch->id, true, '2026-10-02 08:00:00');
    Http::fake([
        '*/health' => Http::response(['status' => 'ok', 'connected_devices_count' => 0]),
        '*/api/devices' => Http::sequence()
            ->push([['device_id' => 'branch14', 'online' => false]])
            ->push([['device_id' => 'branch14', 'online' => true]]),
        '*/api/isapi' => Http::response(['response' => json_encode(['AcsEvent' => ['totalMatches' => 0, 'numOfMatches' => 0, 'InfoList' => []]])]),
    ]);

    $this->artisan('hikvision:healthcheck')->assertSuccessful();
    expect($device->fresh()->is_online)->toBeFalse();
    expect(Cache::has("hikvision_device_down_alert:{$device->id}"))->toBeTrue();

    // Qayta ulanganda kalit tozalanadi
    $this->artisan('hikvision:healthcheck')->assertSuccessful();
    expect(Cache::has("hikvision_device_down_alert:{$device->id}"))->toBeFalse();
});

test('scheduled hikvision commands are skipped while the deploy flag file exists', function () {
    $flag = storage_path('framework/deploying');
    @unlink($flag);

    $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command ?? '', 'hikvision:'));
    expect($events)->not->toBeEmpty();

    foreach ($events as $e) {
        expect($e->filtersPass(app()))->toBeTrue();
    }

    touch($flag);
    try {
        foreach ($events as $e) {
            expect($e->filtersPass(app()))->toBeFalse();
        }

        // Eskirgan (15 daqiqadan eski) bayroq e'tiborga olinmaydi
        touch($flag, time() - 1200);
        foreach ($events as $e) {
            expect($e->filtersPass(app()))->toBeTrue();
        }
    } finally {
        @unlink($flag);
    }
});

test('catch-up job splits a long gap into day windows and keeps timeout below queue retry_after', function () {
    $device = makeIsupDevice($this->branch->id, true, '2026-10-02 11:29:00');
    fakeGatewayOnline();

    (new \App\Jobs\CatchUpDeviceEventsJob($device->id, '2026-09-26 18:00:00'))
        ->handle(app(\App\Services\Hikvision\HikvisionSyncService::class));

    $starts = collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), '/api/isapi'))
        ->map(fn ($pair) => json_decode($pair[0]['body'], true)['AcsEventCond']['startTime'])
        ->values()->all();

    expect($starts)->toBe([
        '2026-09-26T00:00:00+05:00',
        '2026-09-28T00:00:00+05:00',
        '2026-09-30T00:00:00+05:00',
        '2026-10-02T00:00:00+05:00',
    ]);

    $retryAfter = (int) config('queue.connections.database.retry_after', 90);
    expect((new \App\Jobs\CatchUpDeviceEventsJob(1, 'x'))->timeout)->toBeLessThan($retryAfter);
});
