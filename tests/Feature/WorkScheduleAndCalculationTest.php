<?php

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDay;
use App\Models\Branch\BranchHoliday;
use App\Models\Day;
use App\Models\Firm\Firm;
use App\Models\Firm\FirmHoliday;
use App\Models\Hikvision\HikvisionAccess;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\Worker\Worker;
use App\Models\Worker\WorkerDay;
use App\Models\Worker\WorkerHoliday;
use App\Services\Attendance\WorkScheduleService;
use Carbon\Carbon;

beforeEach(function () {
    // Days 1..7 (1=Sunday, 2=Monday, ..., 7=Saturday)
    $dayNames = [
        1 => ['Yakshanba', 'Воскресенье', 'Sunday'],
        2 => ['Dushanba', 'Понедельник', 'Monday'],
        3 => ['Seshanba', 'Вторник', 'Tuesday'],
        4 => ['Chorshanba', 'Среда', 'Wednesday'],
        5 => ['Payshanba', 'Четверг', 'Thursday'],
        6 => ['Juma', 'Пятница', 'Friday'],
        7 => ['Shanba', 'Суббота', 'Saturday'],
    ];

    foreach ($dayNames as $idx => [$uz, $ru, $en]) {
        Day::create([
            'index' => $idx,
            'name' => $uz,
            'name_ru' => $ru,
            'name_en' => $en,
        ]);
    }

    $this->firm = Firm::create([
        'name' => 'Schedule Test Firm',
        'branch_limit' => 10,
        'branch_price' => 100000,
        'valid_date' => now()->addYear(),
    ]);

    $this->branch = Branch::create([
        'name' => 'Schedule Test Branch',
        'firm_id' => $this->firm->id,
        'work_time' => '09:00',
        'end_time' => '18:00',
    ]);

    $this->worker = Worker::create([
        'name' => 'Fargona Worker',
        'branch_id' => $this->branch->id,
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);

    // Set Monday-Friday schedule on branch (indexes 2, 3, 4, 5, 6)
    $monFriDays = Day::whereIn('index', [2, 3, 4, 5, 6])->get();
    foreach ($monFriDays as $d) {
        BranchDay::create([
            'branch_id' => $this->branch->id,
            'day_id' => $d->id,
        ]);
    }
    $this->user = \App\Models\User\User::factory()->create();
});

test('dushanba-juma jadvali bilan 2026-09 oyi 22 ish kuni boladi va bayramlar to\'g\'ri chegiriladi', function () {
    $service = new WorkScheduleService();

    // 1. In September 2026 with Mon-Fri schedule, exactly 22 working days
    $count = $service->countWorkingDays($this->worker, '2026-09-01', '2026-09-30');
    expect($count)->toBe(22);

    // 2. Add Branch Holiday on a Tuesday (2026-09-08) -> 21 days
    BranchHoliday::create([
        'branch_id' => $this->branch->id,
        'date' => '2026-09-08',
        'name' => 'Filial bayrami',
    ]);
    $count = $service->countWorkingDays($this->worker, '2026-09-01', '2026-09-30');
    expect($count)->toBe(21);

    // 3. Add Firm Holiday on a Tuesday (2026-09-15) -> 20 days
    FirmHoliday::create([
        'firm_id' => $this->firm->id,
        'date' => '2026-09-15',
        'name' => 'Firma bayrami',
    ]);
    $count = $service->countWorkingDays($this->worker, '2026-09-01', '2026-09-30');
    expect($count)->toBe(20);

    // 4. Add Worker Holiday on Tuesday-Wednesday (2026-09-22 to 2026-09-23) -> 18 days
    WorkerHoliday::create([
        'worker_id' => $this->worker->id,
        'user_id' => $this->user->id,
        'from' => '2026-09-22',
        'to' => '2026-09-23',
        'comment' => 'Mehnat ta\'tili',
    ]);
    $count = $service->countWorkingDays($this->worker, '2026-09-01', '2026-09-30');
    expect($count)->toBe(18);

    // 5. Add a holiday on a weekend (Sunday 2026-09-06) -> must still be 18 (not deducted twice!)
    BranchHoliday::create([
        'branch_id' => $this->branch->id,
        'date' => '2026-09-06',
        'name' => 'Yakshanba bayrami',
    ]);
    $count = $service->countWorkingDays($this->worker, '2026-09-01', '2026-09-30');
    expect($count)->toBe(18);

    // 6. Test batch calculation gives identical result
    $batch = $service->batchCalculateWorkingDays([$this->worker], '2026-09-01', '2026-09-30');
    expect($batch[$this->worker->id])->toBe(18);

    // 7. Test getOffDayNumbers
    $offDays = $service->getOffDayNumbers($this->worker, '2026-09');
    expect($offDays)->toContain(8)  // Branch holiday
        ->and($offDays)->toContain(15) // Firm holiday
        ->and($offDays)->toContain(22) // Worker holiday
        ->and($offDays)->toContain(23) // Worker holiday
        ->and($offDays)->toContain(6)  // Sunday
        ->and($offDays)->toContain(5); // Saturday
});

test('carbon 3 diffInMinutes sign is positive for late minutes and worked minutes in portal', function () {
    $this->worker->update([
        'password' => 'pass123',
    ]);

    $token = $this->worker->createToken('test', ['worker'])->plainTextToken;

    $access = HikvisionAccess::create([
        'accessControllerEvent' => 'test',
        'dateTime' => Carbon::today()->setTime(9, 25, 0)->format('Y-m-d H:i:s'),
    ]);

    // Check-in at 09:25:00 (Schedule starts 09:00:00 -> 25 mins late)
    $checkIn = new HikvisionAccessEvent([
        'hikvision_access_id' => $access->id,
        'employeeNoString' => $this->worker->employeeNoString,
        'attendanceStatus' => 'checkIn',
    ]);
    $checkIn->created_at = Carbon::today()->setTime(9, 25, 0);
    $checkIn->save();

    // Check-out at 18:00:00 (Worked from 09:25 to 18:00 = 515 mins = 8.58 hours)
    $checkOut = new HikvisionAccessEvent([
        'hikvision_access_id' => $access->id,
        'employeeNoString' => $this->worker->employeeNoString,
        'attendanceStatus' => 'checkOut',
    ]);
    $checkOut->created_at = Carbon::today()->setTime(18, 0, 0);
    $checkOut->save();

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/worker/portal/today');

    $response->assertStatus(200);

    $lateMinutes = $response->json('data.late_minutes');
    $workedHours = $response->json('data.worked_hours');

    expect($lateMinutes)->toBe(25)
        ->and($workedHours)->toBe(8.58);
});
