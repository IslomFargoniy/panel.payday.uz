<?php

use App\Models\Branch\Branch;
use App\Models\Firm\Firm;
use App\Models\Hikvision\HikvisionAccess;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\Worker\Worker;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->firm = Firm::create([
        'name' => 'Smoke Test Firm',
        'status' => 1,
        'valid_date' => now()->addYear()->toDateString(),
        'branch_limit' => 5,
        'branch_price' => 0,
    ]);

    $this->branch = Branch::create([
        'firm_id' => $this->firm->id,
        'name' => 'Smoke Test Branch',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'hour_price' => 20000,
        'fine_price' => 1000,
        'status' => 1,
    ]);

    $this->worker = Worker::create([
        'branch_id' => $this->branch->id,
        'name' => 'Smoke Test Worker',
        'phone' => '998901234567',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'hour_price' => 20000,
        'fine_price' => 1000,
        'status' => 1,
    ]);

    $ha = HikvisionAccess::create(['shortSerialNumber' => 'AE7106709', 'dateTime' => '2026-10-01 08:55:00']);
    $event = new HikvisionAccessEvent([
        'employeeNoString' => (string) $this->worker->employeeNoString,
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
        'work_time' => '09:00:00',
    ]);
    $event->hikvision_access_id = $ha->id;
    $event->timestamps = false;
    $event->created_at = '2026-10-01 08:55:00';
    $event->updated_at = '2026-10-01 08:55:00';
    $event->save();
});

test('attendance:smoke-test executes all read-only checks successfully and returns exit code 0', function () {
    $initialWorkersCount = Worker::count();
    $initialEventsCount = HikvisionAccessEvent::count();

    $this->artisan('attendance:smoke-test --date=2026-10-01')
        ->assertExitCode(0)
        ->expectsOutputToContain('dashboard')
        ->expectsOutputToContain('salary_report')
        ->expectsOutputToContain('monthly_attendance')
        ->expectsOutputToContain('attendance_grid')
        ->expectsOutputToContain('daily_attendance')
        ->expectsOutputToContain('mobile_myAttendance')
        ->expectsOutputToContain('OK');

    // Verify read-only guarantee: nothing was inserted or modified
    expect(Worker::count())->toBe($initialWorkersCount)
        ->and(HikvisionAccessEvent::count())->toBe($initialEventsCount);
});

test('attendance:smoke-test returns non-zero exit code on invalid date format', function () {
    $this->artisan('attendance:smoke-test --date=invalid-date')
        ->assertExitCode(1)
        ->expectsOutputToContain("Noto'g'ri sana formati");
});
