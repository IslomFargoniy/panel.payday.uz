<?php

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDevice;
use App\Models\Firm\Firm;
use App\Models\Hikvision\HikvisionAccess;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\User\User;
use App\Models\Worker\Worker;
use App\Services\Hikvision\AttendanceStatusResolver;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->firm = Firm::create([
        'name' => 'Status Firm',
        'branch_limit' => 5,
        'branch_price' => 100000,
        'valid_date' => now()->addYear(),
    ]);
    $this->branch = Branch::create([
        'name' => 'Status Branch',
        'firm_id' => $this->firm->id,
        'work_time' => '09:00',
        'end_time' => '18:00',
    ]);
    $this->worker = Worker::factory()->create([
        'branch_id' => $this->branch->id,
        'employeeNoString' => '99001',
    ]);
});

test('kecha oxirgi event checkIn bo\'lsa, bugungi statussiz birinchi event checkIn bo\'lishi kerak', function () {
    // Yesterday: worker checked in at 09:00 and never checked out
    $yesterdayAccess = HikvisionAccess::create([
        'macAddress' => 'AA:BB:CC:11:22:33',
        'dateTime' => '2026-09-30 09:00:00',
        'shortSerialNumber' => 'DEV_01',
    ]);
    HikvisionAccessEvent::create([
        'hikvision_access_id' => $yesterdayAccess->id,
        'employeeNoString' => '99001',
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
    ]);

    // Today at 09:05: first event with missing/empty status
    $resolvedStatus = AttendanceStatusResolver::resolve(
        rawStatus: null,
        employeeNoString: '99001',
        dateTimeStr: '2026-10-01 09:05:00'
    );

    // It MUST be checkIn because there are no prior events TODAY
    expect($resolvedStatus)->toBe('checkIn');
});

test('bugun checkIn dan keyin kelgan statussiz event checkOut bo\'lishi kerak', function () {
    // Today at 09:00: worker checked in
    $todayAccess = HikvisionAccess::create([
        'macAddress' => 'AA:BB:CC:11:22:33',
        'dateTime' => '2026-10-01 09:00:00',
        'shortSerialNumber' => 'DEV_01',
    ]);
    HikvisionAccessEvent::create([
        'hikvision_access_id' => $todayAccess->id,
        'employeeNoString' => '99001',
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
    ]);

    // Today at 18:00: second event with missing/empty status
    $resolvedStatus = AttendanceStatusResolver::resolve(
        rawStatus: '',
        employeeNoString: '99001',
        dateTimeStr: '2026-10-01 18:00:00'
    );

    // It MUST be checkOut because earlier today there was a checkIn
    expect($resolvedStatus)->toBe('checkOut');
});

test('HikvisionController callback resolves missing status scoped to current day', function () {
    $device = BranchDevice::create([
        'branch_id' => $this->branch->id,
        'mac_address' => '11:22:33:44:55:66',
        'device_id' => 'DEV_STATUS_TEST',
        'status' => true,
    ]);

    $empNo = (string) $this->worker->employeeNoString;

    // Yesterday event: checkIn
    $yesterdayAccess = HikvisionAccess::create([
        'macAddress' => '11:22:33:44:55:66',
        'dateTime' => '2026-09-30 08:50:00',
        'shortSerialNumber' => 'DEV_STATUS_TEST',
    ]);
    HikvisionAccessEvent::create([
        'hikvision_access_id' => $yesterdayAccess->id,
        'employeeNoString' => $empNo,
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
    ]);

    // Today callback with NO attendanceStatus
    $payload = [
        'dateTime' => '2026-10-01T09:00:00+05:00',
        'macAddress' => '11:22:33:44:55:66',
        'AccessControllerEvent' => [
            'employeeNoString' => $empNo,
            'deviceName' => 'Test Terminal',
            // Notice: attendanceStatus is omitted or empty
        ],
    ];

    $response = $this->postJson('/api/hikvision-callback', $payload);
    $response->assertOk();

    $todayEvent = HikvisionAccessEvent::where('employeeNoString', $empNo)
        ->whereHas('hikvisionAccess', function ($q) {
            $q->whereDate('dateTime', '2026-10-01');
        })
        ->first();

    expect($todayEvent)->not->toBeNull()
        ->and($todayEvent->attendanceStatus)->toBe('checkIn');
});
