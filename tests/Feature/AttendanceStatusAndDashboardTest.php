<?php

use App\Enums\AttendanceStatus;
use App\Models\Branch\Branch;
use App\Models\Firm\Firm;
use App\Models\Hikvision\HikvisionAccess;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\User\User;
use App\Models\Worker\Worker;
use App\Models\Worker\WorkerHoliday;
use App\Services\Attendance\AttendancePairingService;
use App\Services\Dashboard\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;

test('AttendanceStatus enum normalizes statuses and labels accurately', function () {
    expect(AttendanceStatus::normalize('CheckIn'))->toBe('checkIn');
    expect(AttendanceStatus::normalize('keldi'))->toBe('checkIn');
    expect(AttendanceStatus::normalize('breakIn'))->toBe('checkIn');
    expect(AttendanceStatus::normalize('entered'))->toBe('checkIn');

    expect(AttendanceStatus::normalize('CheckOut'))->toBe('checkOut');
    expect(AttendanceStatus::normalize('ketdi'))->toBe('checkOut');
    expect(AttendanceStatus::normalize('breakOut'))->toBe('checkOut');
    expect(AttendanceStatus::normalize('exited'))->toBe('checkOut');

    expect(AttendanceStatus::normalize('undefined'))->toBeNull();
    expect(AttendanceStatus::normalize(''))->toBeNull();
    expect(AttendanceStatus::normalize(null))->toBeNull();

    expect(AttendanceStatus::label('checkIn'))->toBe('Keldi');
    expect(AttendanceStatus::label('checkOut'))->toBe('Ketdi');
    expect(AttendanceStatus::label('breakOut'))->toBe('Ketdi');
});

test('hikvision:normalize-statuses command dry-run and force execution', function () {
    $ha = HikvisionAccess::create(['shortSerialNumber' => 'TEST', 'dateTime' => '2026-10-01 12:00:00']);

    $e1 = new HikvisionAccessEvent([
        'employeeNoString' => '3001',
        'attendanceStatus' => 'breakOut',
        'label' => 'breakOut',
    ]);
    $e1->hikvision_access_id = $ha->id;
    $e1->save();

    $e2 = new HikvisionAccessEvent([
        'employeeNoString' => '3002',
        'attendanceStatus' => 'keldi',
        'label' => 'keldi',
    ]);
    $e2->hikvision_access_id = $ha->id;
    $e2->save();

    // Dry-run
    $this->artisan('hikvision:normalize-statuses', ['--dry-run' => true])
        ->expectsOutputToContain('breakOut')
        ->expectsOutputToContain('keldi')
        ->assertSuccessful();

    expect($e1->fresh()->attendanceStatus)->toBe('breakOut');
    expect($e2->fresh()->attendanceStatus)->toBe('keldi');

    // Force
    $this->artisan('hikvision:normalize-statuses', ['--force' => true])
        ->expectsOutputToContain('Muvaffaqiyatli yakunlandi')
        ->assertSuccessful();

    expect($e1->fresh()->attendanceStatus)->toBe('checkOut');
    expect($e1->fresh()->label)->toBe('Ketdi');
    expect($e2->fresh()->attendanceStatus)->toBe('checkIn');
    expect($e2->fresh()->label)->toBe('Keldi');
});

test('Dashboard absent count does not double deduct workers who are on holiday and checked in', function () {
    $firm = Firm::create([
        'name' => 'Dash Firm',
        'branch_limit' => 10,
        'branch_price' => 100000,
        'valid_date' => now()->addYear(),
    ]);
    $branch = Branch::create([
        'name' => 'Dash Branch',
        'firm_id' => $firm->id,
        'work_time' => '09:00',
        'end_time' => '18:00',
    ]);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $today = date('Y-m-d');

    // Worker 1: Normal came
    $w1 = Worker::create([
        'name' => 'Worker 1',
        'branch_id' => $branch->id,
        'employeeNoString' => '4001',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'status' => 1,
    ]);

    // Worker 2: On holiday and DID check in
    $w2 = Worker::create([
        'name' => 'Worker 2 (Holiday & Came)',
        'branch_id' => $branch->id,
        'employeeNoString' => '4002',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'status' => 1,
    ]);
    WorkerHoliday::create([
        'worker_id' => $w2->id,
        'from' => $today,
        'to' => $today,
    ]);

    // Worker 3: On holiday and did NOT check in
    $w3 = Worker::create([
        'name' => 'Worker 3 (Holiday & Absent)',
        'branch_id' => $branch->id,
        'employeeNoString' => '4003',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'status' => 1,
    ]);
    WorkerHoliday::create([
        'worker_id' => $w3->id,
        'from' => $today,
        'to' => $today,
    ]);

    // Worker 4: Purely absent
    $w4 = Worker::create([
        'name' => 'Worker 4 (Pure Absent)',
        'branch_id' => $branch->id,
        'employeeNoString' => '4004',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'status' => 1,
    ]);

    // Create checkIn events for w1 and w2
    $ha1 = HikvisionAccess::create(['shortSerialNumber' => 'AE7106709', 'dateTime' => "{$today} 08:55:00"]);
    $e1 = new HikvisionAccessEvent([
        'employeeNoString' => (string) $w1->employeeNoString,
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
        'work_time' => '09:00:00',
    ]);
    $e1->hikvision_access_id = $ha1->id;
    $e1->timestamps = false;
    $e1->created_at = Carbon::parse("{$today} 08:55:00");
    $e1->updated_at = Carbon::parse("{$today} 08:55:00");
    $e1->save();

    $ha2 = HikvisionAccess::create(['shortSerialNumber' => 'AE7106709', 'dateTime' => "{$today} 09:05:00"]);
    $e2 = new HikvisionAccessEvent([
        'employeeNoString' => (string) $w2->employeeNoString,
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
        'work_time' => '09:00:00',
    ]);
    $e2->hikvision_access_id = $ha2->id;
    $e2->timestamps = false;
    $e2->created_at = Carbon::parse("{$today} 09:05:00");
    $e2->updated_at = Carbon::parse("{$today} 09:05:00");
    $e2->save();

    $dashboardService = app(DashboardService::class);
    $req = new Request(['branch_id' => $branch->id]);
    $data = $dashboardService->getDashboardData($req);

    // Total: 4 workers
    // Came: 2 (w1 on_time, w2 late)
    // On holiday total: 2 (w2, w3)
    // Absent should be exactly 1 (w4), NOT negative or 0!
    expect($data['stats']['all_worker'])->toBe(4);
    expect($data['stats']['on_time'])->toBe(1);
    expect($data['stats']['late'])->toBe(1);
    expect($data['stats']['on_holiday'])->toBe(2);
    expect($data['stats']['absent'])->toBe(1);
});

test('daily_attendance pairs night shift ending next day before 12:00 and computes max late minutes', function () {
    $firm = Firm::create([
        'name' => 'Night Shift Firm',
        'branch_limit' => 10,
        'branch_price' => 100000,
        'valid_date' => now()->addYear(),
    ]);
    $branch = Branch::create([
        'name' => 'Night Branch',
        'firm_id' => $firm->id,
        'work_time' => '22:00',
        'end_time' => '06:00',
    ]);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $w = Worker::create([
        'name' => 'Night Worker',
        'branch_id' => $branch->id,
        'work_time' => '22:00:00',
        'end_time' => '06:00:00',
        'status' => 1,
    ]);

    // CheckIn at 22:30 on day 1 (30 mins late)
    $haIn = HikvisionAccess::create(['shortSerialNumber' => 'AE7106709', 'dateTime' => '2026-10-01 22:30:00']);
    $eIn = new HikvisionAccessEvent([
        'employeeNoString' => (string) $w->employeeNoString,
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
        'work_time' => '22:00:00',
    ]);
    $eIn->hikvision_access_id = $haIn->id;
    $eIn->timestamps = false;
    $eIn->created_at = Carbon::parse('2026-10-01 22:30:00');
    $eIn->updated_at = Carbon::parse('2026-10-01 22:30:00');
    $eIn->save();

    // CheckOut at 06:15 on day 2 (next morning)
    $haOut = HikvisionAccess::create(['shortSerialNumber' => 'AE7106709', 'dateTime' => '2026-10-02 06:15:00']);
    $eOut = new HikvisionAccessEvent([
        'employeeNoString' => (string) $w->employeeNoString,
        'attendanceStatus' => 'checkOut',
        'label' => 'Ketdi',
        'work_time' => '22:00:00',
    ]);
    $eOut->hikvision_access_id = $haOut->id;
    $eOut->timestamps = false;
    $eOut->created_at = Carbon::parse('2026-10-02 06:15:00');
    $eOut->updated_at = Carbon::parse('2026-10-02 06:15:00');
    $eOut->save();

    $response = $this->get("/daily_attendance/{$branch->id}?date=2026-10-01");

    $response->assertSuccessful();
    $workerProp = $response->viewData('page')['props']['worker']['data'][0];

    // The shift started on 2026-10-01 must pair with the checkout on 2026-10-02
    expect($workerProp['late_minutes'])->toBe(30);
    // 22:30 to 06:15 = 7h 45m = 465 minutes
    expect($workerProp['worked_minutes'])->toBe(465);
    expect($workerProp['paired_events'])->toHaveCount(1);
    expect($workerProp['paired_events'][0]->to_time)->toBe('2026-10-02 06:15:00');
});

