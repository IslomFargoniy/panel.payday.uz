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
        'employeeNoString' => 'EMP_SMOKE_1',
    ]);

    $haIn = HikvisionAccess::create(['shortSerialNumber' => 'AE7106709', 'dateTime' => '2026-10-01 08:55:00']);
    $eventIn = new HikvisionAccessEvent([
        'employeeNoString' => (string) $this->worker->employeeNoString,
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
        'work_time' => '09:00:00',
    ]);
    $eventIn->hikvision_access_id = $haIn->id;
    $eventIn->timestamps = false;
    $eventIn->created_at = '2026-10-01 08:55:00';
    $eventIn->updated_at = '2026-10-01 08:55:00';
    $eventIn->save();

    $haOut = HikvisionAccess::create(['shortSerialNumber' => 'AE7106709', 'dateTime' => '2026-10-01 18:00:00']);
    $eventOut = new HikvisionAccessEvent([
        'employeeNoString' => (string) $this->worker->employeeNoString,
        'attendanceStatus' => 'checkOut',
        'label' => 'Ketdi',
        'work_time' => '18:00:00',
    ]);
    $eventOut->hikvision_access_id = $haOut->id;
    $eventOut->timestamps = false;
    $eventOut->created_at = '2026-10-01 18:00:00';
    $eventOut->updated_at = '2026-10-01 18:00:00';
    $eventOut->save();
});

test('attendance:smoke-test executes all read-only checks successfully and returns exit code 0', function () {
    $initialWorkersCount = Worker::count();
    $initialEventsCount = HikvisionAccessEvent::count();

    $mutatingQueries = [];
    \Illuminate\Support\Facades\DB::listen(function ($query) use (&$mutatingQueries) {
        $sql = trim(strtoupper($query->sql));
        if (str_starts_with($sql, 'INSERT') || str_starts_with($sql, 'UPDATE') || str_starts_with($sql, 'DELETE')) {
            $mutatingQueries[] = $query->sql;
        }
    });

    $exitCode = \Illuminate\Support\Facades\Artisan::call('attendance:smoke-test', [
        '--date' => '2026-10-01',
    ]);
    expect($exitCode)->toBe(0);

    $output = \Illuminate\Support\Facades\Artisan::output();
    expect($output)
        ->toContain('dashboard')
        ->toContain('salary_report')
        ->toContain('monthly_attendance')
        ->toContain('attendance_grid')
        ->toContain('daily_attendance')
        ->toContain('mobile_myAttendance')
        ->toContain('OK');

    // Read-only guarantee: zero mutating queries and zero count changes
    expect($mutatingQueries)->toBeEmpty()
        ->and(Worker::count())->toBe($initialWorkersCount)
        ->and(HikvisionAccessEvent::count())->toBe($initialEventsCount);

    // Verify row counts for all checks are greater than 0
    preg_match_all('/\|\s+([^|]+?)\s+\|\s+(OK|FAIL)\s+\|\s+([0-9\.]+ms)\s+\|\s+(\d+)\s+\|/', $output, $matches, PREG_SET_ORDER);
    expect($matches)->not->toBeEmpty();
    foreach ($matches as $match) {
        $checkName = trim($match[1]);
        $status = $match[2];
        $rowCount = (int) $match[4];
        expect($status)->toBe('OK');
        expect($rowCount)->toBeGreaterThan(0, "Check '{$checkName}' must have rows > 0");
    }
});

test('attendance:smoke-test returns non-zero exit code on invalid date format', function () {
    $this->artisan('attendance:smoke-test --date=invalid-date')
        ->assertExitCode(1)
        ->expectsOutputToContain("Noto'g'ri sana formati");
});
