<?php

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDevice;
use App\Models\Firm\Firm;
use App\Models\Hikvision\HikvisionAccess;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\Worker\Worker;
use App\Services\Hikvision\EventTimeResolver;
use App\Services\Hikvision\HikvisionSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $firm = Firm::create([
        'name' => 'Late Firm',
        'status' => 1,
        'branch_limit' => 5,
        'branch_price' => 0,
        'valid_date' => now()->addYear()->toDateString(),
    ]);
    $this->branch = Branch::create([
        'name' => 'Balam 1',
        'firm_id' => $firm->id,
        'work_time' => '09:00',
        'end_time' => '18:00',
    ]);
    $this->worker = Worker::factory()->create(['branch_id' => $this->branch->id]);
    // employeeNoString model tomonidan avtomatik belgilanadi
    $this->emp = (string) $this->worker->fresh()->employeeNoString;
    $this->device = BranchDevice::create([
        'branch_id' => $this->branch->id,
        'name' => 'Balam ISUP',
        'mac_address' => '00:11:22:33:44:99',
        'device_id' => 'branch14',
        'connection_type' => 'isup',
        'status' => 1,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-10-02 11:29:32', 'Asia/Tashkent'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeEventAt(string $deviceTime, string $createdAt, ?string $emp = null): HikvisionAccessEvent
{
    $ha = HikvisionAccess::create(['shortSerialNumber' => 'branch14', 'dateTime' => $deviceTime]);
    $e = new HikvisionAccessEvent(['employeeNoString' => $emp ?? test()->emp, 'name' => 'W', 'attendanceStatus' => 'checkIn']);
    $e->hikvision_access_id = $ha->id;
    $e->created_at = $createdAt;
    $e->updated_at = $createdAt;
    $e->save();

    return $e;
}

function fakeAcsEvents(array $events): void
{
    Http::fake([
        '*' => Http::response([
            'response' => json_encode(['AcsEvent' => [
                'totalMatches' => count($events),
                'numOfMatches' => count($events),
                'InfoList' => $events,
            ]]),
        ], 200),
    ]);
}

test('correctIfLate moves created_at to device time for late-recorded events only', function () {
    $resolver = app(EventTimeResolver::class);

    $late = makeEventAt('2026-10-01 07:58:10', '2026-10-02 11:29:32');
    expect($resolver->correctIfLate($late, '2026-10-01 07:58:10', 'branch14'))->toBeTrue();
    expect($late->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 07:58:10');

    // Soat oldinda (created_at < dateTime): tegilmaydi
    $ahead = makeEventAt('2026-10-02 09:02:15', '2026-10-02 09:00:00');
    expect($resolver->correctIfLate($ahead, '2026-10-02 09:02:15', 'GF0132950'))->toBeFalse();
    expect($ahead->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-02 09:00:00');

    // Kichik kechikish (<= 600s): tegilmaydi
    $small = makeEventAt('2026-10-02 09:00:00', '2026-10-02 09:05:00');
    expect($resolver->correctIfLate($small, '2026-10-02 09:00:00'))->toBeFalse();

    // 45 kundan eski: tegilmaydi
    $old = makeEventAt('2026-08-01 09:00:00', '2026-10-02 09:00:00');
    expect($resolver->correctIfLate($old, '2026-08-01 09:00:00'))->toBeFalse();

    // Qoida o'chirilgan
    Config::set('hikvision.late_delivery_seconds', 0);
    $off = makeEventAt('2026-10-01 08:00:00', '2026-10-02 11:00:00');
    expect($resolver->correctIfLate($off, '2026-10-01 08:00:00'))->toBeFalse();
});

test('soft-deleted events are never corrected', function () {
    $e = makeEventAt('2026-10-01 07:58:10', '2026-10-02 11:29:32');
    $e->delete();

    expect(app(EventTimeResolver::class)->correctIfLate($e->fresh() ?? HikvisionAccessEvent::withTrashed()->find($e->id), '2026-10-01 07:58:10'))->toBeFalse();
    expect(HikvisionAccessEvent::withTrashed()->find($e->id)->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-02 11:29:32');
});

test('sync corrects created_at of an event previously stored by callback with server time, without creating a duplicate', function () {
    // 1. Eski xatti-harakat: callback kechikkan eventni server vaqti bilan yozadi
    Config::set('hikvision.late_delivery_seconds', 0);

    $this->postJson('/api/hikvision-callback', [
        'dateTime' => '2026-10-01T07:58:10+05:00',
        'macAddress' => '00:11:22:33:44:99',
        'AccessControllerEvent' => ['employeeNoString' => $this->emp, 'deviceName' => 'Terminal'],
    ])->assertOk();

    $event = HikvisionAccessEvent::where('employeeNoString', $this->emp)->firstOrFail();
    expect($event->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-02 11:29:32');

    // 2. Yangi qoida yoqilgan, sync xuddi shu eventni qurilmadan oladi
    Config::set('hikvision.late_delivery_seconds', 600);
    fakeAcsEvents([['employeeNoString' => $this->emp, 'time' => '2026-10-01T07:58:10+05:00', 'serialNo' => 1]]);

    $res = app(HikvisionSyncService::class)->syncEventsFromDevice($this->device->fresh());

    expect($res['success'])->toBeTrue()
        ->and($res['synced_count'])->toBe(0)
        ->and($res['corrected_count'])->toBe(1);
    expect(HikvisionAccessEvent::where('employeeNoString', $this->emp)->count())->toBe(1);
    expect($event->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 07:58:10');
});

test('callback duplicate path corrects an already stored late event', function () {
    $e = makeEventAt('2026-10-01 07:58:10', '2026-10-02 11:29:32');

    $this->postJson('/api/hikvision-callback', [
        'dateTime' => '2026-10-01T07:58:10+05:00',
        'macAddress' => '00:11:22:33:44:99',
        'AccessControllerEvent' => ['employeeNoString' => $this->emp, 'deviceName' => 'Terminal'],
    ])->assertOk();

    expect(HikvisionAccessEvent::where('employeeNoString', $this->emp)->count())->toBe(1);
    expect($e->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 07:58:10');
});

test('new late callback event is written with device time (offline backlog)', function () {
    $this->postJson('/api/hikvision-callback', [
        'dateTime' => '2026-10-01T07:58:10+05:00',
        'macAddress' => '00:11:22:33:44:99',
        'AccessControllerEvent' => ['employeeNoString' => $this->emp, 'deviceName' => 'Terminal'],
    ])->assertOk();

    $e = HikvisionAccessEvent::where('employeeNoString', $this->emp)->firstOrFail();
    expect($e->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 07:58:10');
});
