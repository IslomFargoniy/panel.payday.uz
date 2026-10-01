<?php

use App\Models\Branch\Branch;
use App\Models\Firm\Firm;
use App\Models\Hikvision\HikvisionAccess;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\User\User;
use App\Models\Worker\Worker;
use App\Services\Dashboard\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->firm = Firm::create([
        'name' => 'Test Firm',
        'status' => 1,
        'valid_date' => now()->addYear(),
        'branch_limit' => 5,
        'branch_price' => 0,
    ]);

    $this->branch = Branch::create([
        'firm_id' => $this->firm->id,
        'name' => 'Main Branch',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'hour_price' => 20000,
        'fine_price' => 1000,
        'status' => 1,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('03:00 arrival with 09:00 work_time is not counted as first check_in of the day', function () {
    $today = date('Y-m-d');

    // Worker 1: work_time 09:00. Has 03:00 early event and 09:05 shift arrival.
    $w1 = Worker::create([
        'name' => 'Worker Late Shift',
        'branch_id' => $this->branch->id,
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'status' => 1,
    ]);

    $haEarly = HikvisionAccess::create(['shortSerialNumber' => 'AE7106709', 'dateTime' => "{$today} 03:00:00"]);
    $eEarly = new HikvisionAccessEvent([
        'employeeNoString' => (string) $w1->employeeNoString,
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
        'work_time' => '09:00:00',
    ]);
    $eEarly->hikvision_access_id = $haEarly->id;
    $eEarly->timestamps = false;
    $eEarly->created_at = Carbon::parse("{$today} 03:00:00");
    $eEarly->updated_at = Carbon::parse("{$today} 03:00:00");
    $eEarly->save();

    $haShift = HikvisionAccess::create(['shortSerialNumber' => 'AE7106709', 'dateTime' => "{$today} 09:05:00"]);
    $eShift = new HikvisionAccessEvent([
        'employeeNoString' => (string) $w1->employeeNoString,
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
        'work_time' => '09:00:00',
    ]);
    $eShift->hikvision_access_id = $haShift->id;
    $eShift->timestamps = false;
    $eShift->created_at = Carbon::parse("{$today} 09:05:00");
    $eShift->updated_at = Carbon::parse("{$today} 09:05:00");
    $eShift->save();

    // Worker 2: work_time 09:00, ONLY has a 03:00 check_in (e.g. night tap), didn't come for day shift
    $w2 = Worker::create([
        'name' => 'Worker Only Night Tap',
        'branch_id' => $this->branch->id,
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'status' => 1,
    ]);

    $haNightOnly = HikvisionAccess::create(['shortSerialNumber' => 'AE7106709', 'dateTime' => "{$today} 03:00:00"]);
    $eNightOnly = new HikvisionAccessEvent([
        'employeeNoString' => (string) $w2->employeeNoString,
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
        'work_time' => '09:00:00',
    ]);
    $eNightOnly->hikvision_access_id = $haNightOnly->id;
    $eNightOnly->timestamps = false;
    $eNightOnly->created_at = Carbon::parse("{$today} 03:00:00");
    $eNightOnly->updated_at = Carbon::parse("{$today} 03:00:00");
    $eNightOnly->save();

    $dashboardService = app(DashboardService::class);
    $req = new Request(['branch_id' => $this->branch->id]);
    $data = $dashboardService->getDashboardData($req);

    // Worker 1 must be counted as late (09:05 > 09:00), NOT on_time (03:00 is ignored).
    // Worker 2's 03:00 is ignored, so worker 2 is not counted as on_time.
    expect($data['stats']['all_worker'])->toBe(2)
        ->and($data['stats']['late'])->toBe(1)
        ->and($data['stats']['on_time'])->toBe(0)
        ->and($data['stats']['absent'])->toBe(1);
});

test('hae.work_time is prioritized over current worker work_time in dashboard calculation', function () {
    $today = date('Y-m-d');

    // Worker 1: current DB work_time is 10:00, but event work_time was 09:00.
    // Checked in at 09:15.
    // With 09:00 work_time -> late (09:15 > 09:00).
    // If current DB work_time was wrongly used -> on_time (09:15 <= 10:00).
    $w1 = Worker::create([
        'name' => 'Worker Changed WorkTime Later',
        'branch_id' => $this->branch->id,
        'work_time' => '10:00:00',
        'end_time' => '19:00:00',
        'status' => 1,
    ]);

    $ha1 = HikvisionAccess::create(['shortSerialNumber' => 'AE7106709', 'dateTime' => "{$today} 09:15:00"]);
    $e1 = new HikvisionAccessEvent([
        'employeeNoString' => (string) $w1->employeeNoString,
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
        'work_time' => '09:00:00', // Event snapshot work_time
    ]);
    $e1->hikvision_access_id = $ha1->id;
    $e1->timestamps = false;
    $e1->created_at = Carbon::parse("{$today} 09:15:00");
    $e1->updated_at = Carbon::parse("{$today} 09:15:00");
    $e1->save();

    // Worker 2: current DB work_time is 08:30, but event work_time was 09:30.
    // Checked in at 09:00.
    // With 09:30 event work_time -> on_time (09:00 <= 09:30).
    // If current DB work_time was wrongly used -> late (09:00 > 08:30).
    $w2 = Worker::create([
        'name' => 'Worker Changed WorkTime Earlier',
        'branch_id' => $this->branch->id,
        'work_time' => '08:30:00',
        'end_time' => '17:30:00',
        'status' => 1,
    ]);

    $ha2 = HikvisionAccess::create(['shortSerialNumber' => 'AE7106709', 'dateTime' => "{$today} 09:00:00"]);
    $e2 = new HikvisionAccessEvent([
        'employeeNoString' => (string) $w2->employeeNoString,
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
        'work_time' => '09:30:00', // Event snapshot work_time
    ]);
    $e2->hikvision_access_id = $ha2->id;
    $e2->timestamps = false;
    $e2->created_at = Carbon::parse("{$today} 09:00:00");
    $e2->updated_at = Carbon::parse("{$today} 09:00:00");
    $e2->save();

    $dashboardService = app(DashboardService::class);
    $req = new Request(['branch_id' => $this->branch->id]);
    $data = $dashboardService->getDashboardData($req);

    expect($data['stats']['all_worker'])->toBe(2)
        ->and($data['stats']['late'])->toBe(1)
        ->and($data['stats']['on_time'])->toBe(1);
});
