<?php

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDay;
use App\Models\Branch\BranchDevice;
use App\Models\Branch\BranchHoliday;
use App\Models\Day;
use App\Models\Firm\Firm;
use App\Models\Firm\FirmHoliday;
use App\Models\Salary\Salary;
use App\Models\Salary\SalaryPayment;
use App\Models\User\User;
use App\Models\User\UserFirm;
use App\Models\User\UserTopUp;
use App\Models\Worker\Worker;
use App\Models\Worker\WorkerDay;
use App\Models\Worker\WorkerHoliday;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->firm = Firm::create([
        'name' => 'API Consistency Firm',
        'branch_limit' => 5,
        'branch_price' => 100000,
        'valid_date' => now()->addYear(),
    ]);

    UserFirm::create([
        'user_id' => $this->user->id,
        'firm_id' => $this->firm->id,
    ]);

    $this->branch = Branch::create([
        'name' => 'API Consistency Branch',
        'firm_id' => $this->firm->id,
        'work_time' => '09:00',
        'end_time' => '18:00',
    ]);

    // Create days 1..7
    for ($i = 1; $i <= 7; $i++) {
        Day::firstOrCreate(['id' => $i], [
            'index' => $i,
            'name' => 'Day ' . $i,
            'name_ru' => 'Day Ru ' . $i,
            'name_en' => 'Day En ' . $i,
        ]);
    }

    $this->worker = Worker::create([
        'name' => 'API Worker',
        'branch_id' => $this->branch->id,
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'hour_price' => 50000,
        'fine_price' => 10000,
    ]);
});

test('Worker and Branch alias relations work correctly without RelationNotFoundException', function () {
    BranchDay::create(['branch_id' => $this->branch->id, 'day_id' => 1]);
    BranchHoliday::create(['branch_id' => $this->branch->id, 'name' => 'B Holiday', 'date' => '2026-05-01']);
    BranchDevice::create([
        'branch_id' => $this->branch->id,
        'name' => 'Test Dev',
        'device_id' => 'DEV_TEST_01',
        'mac_address' => '00:11:22:33:44:55',
        'status' => 1,
    ]);

    WorkerDay::create(['worker_id' => $this->worker->id, 'day_id' => 1]);
    WorkerHoliday::create(['user_id' => $this->user->id, 'worker_id' => $this->worker->id, 'from' => '2026-05-01', 'to' => '2026-05-02']);

    // Branch aliases: days, holidays, devices
    $branchLoaded = Branch::with(['days', 'holidays', 'devices'])->find($this->branch->id);
    expect($branchLoaded->days)->toHaveCount(1);
    expect($branchLoaded->holidays)->toHaveCount(1);
    expect($branchLoaded->devices)->toHaveCount(1);

    // Worker aliases: days, holidays, hikvision_access_events
    $workerLoaded = Worker::with(['days', 'holidays', 'hikvision_access_events'])->find($this->worker->id);
    expect($workerLoaded->days)->toHaveCount(1);
    expect($workerLoaded->holidays)->toHaveCount(1);
    expect($workerLoaded->hikvision_access_events)->toBeEmpty();
});

test('Worker avatar mutator normalizes /storage/ and storage/ prefixes', function () {
    $this->worker->avatar = '/storage/avatars/sample123.jpg';
    $this->worker->save();
    expect($this->worker->avatar)->toBe('avatars/sample123.jpg');

    $this->worker->avatar = 'storage/avatars/sample456.jpg';
    $this->worker->save();
    expect($this->worker->avatar)->toBe('avatars/sample456.jpg');

    $this->worker->avatar = 'avatars/sample789.jpg';
    $this->worker->save();
    expect($this->worker->avatar)->toBe('avatars/sample789.jpg');
});

test('worker:clean-avatar-paths artisan command detects and cleans legacy avatar paths', function () {
    // Insert raw paths bypassing mutator via query builder
    DB::table('workers')->where('id', $this->worker->id)->update([
        'avatar' => '/storage/avatars/legacy.jpg',
    ]);

    $this->artisan('worker:clean-avatar-paths --dry-run')
        ->assertExitCode(0)
        ->expectsOutputToContain('[DRY-RUN]');

    $this->artisan('worker:clean-avatar-paths --force')
        ->assertExitCode(0)
        ->expectsOutputToContain('muvaffaqiyatli tozalandi');

    $cleanedWorker = Worker::find($this->worker->id);
    expect($cleanedWorker->avatar)->toBe('avatars/legacy.jpg');
});

test('User model has avatar column in table, in fillable and has user_top_up_cleint alias', function () {
    expect(Schema::hasColumn('users', 'avatar'))->toBeTrue();

    $this->user->update([
        'avatar' => 'https://lh3.googleusercontent.com/a/avatar.jpg',
    ]);

    expect($this->user->fresh()->avatar)->toBe('https://lh3.googleusercontent.com/a/avatar.jpg');

    UserTopUp::create([
        'user_id' => $this->user->id,
        'client_id' => $this->user->id,
        'amount' => 50000,
        'transaction_id' => 12345,
    ]);

    expect($this->user->user_top_up_client)->toHaveCount(1);
    expect($this->user->user_top_up_cleint)->toHaveCount(1);
});

test('AttendanceApiController endpoints return JSON data without extractInertiaProps', function () {
    $token = $this->user->createToken('panel-token', ['panel'])->plainTextToken;

    // Daily attendance
    $dailyRes = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/attendance/daily/' . $this->branch->id);
    $dailyRes->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'worker',
                'branch',
            ],
        ]);

    // Monthly attendance
    $monthlyRes = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/attendance/monthly');
    $monthlyRes->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'worker',
                'firms',
                'branches',
            ],
        ]);

    // Attendance grid
    $gridRes = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/attendance/grid');
    $gridRes->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'worker',
                'daysInMonth',
                'firms',
                'branches',
            ],
        ]);
});

test('SalaryApiController salaryReport, calculateSalary and storePayment work cleanly', function () {
    $token = $this->user->createToken('panel-token', ['panel'])->plainTextToken;

    // Salary Report
    $reportRes = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/salary/report?worker_id=' . $this->worker->id);
    $reportRes->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'report',
                'attendance',
                'firms',
                'branches',
                'workers',
            ],
        ]);

    // Calculate Salary via API
    $calcRes = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson('/api/salary/calculate', [
            'worker_id' => $this->worker->id,
            'amount' => 500000,
            'worked_minute' => 600,
            'break_minute' => 0,
            'hour_price' => 50000,
            'from' => '2026-08-01',
            'to' => '2026-08-15',
            'comment' => 'API Calculated Salary',
        ]);
    $calcRes->assertStatus(201)
        ->assertJson(['success' => true]);

    expect(Salary::where('worker_id', $this->worker->id)->where('from', '2026-08-01')->exists())->toBeTrue();

    // Store Payment via API
    $payRes = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson('/api/salary/payments', [
            'worker_id' => $this->worker->id,
            'amount' => 200000,
            'comment' => 'Avans tolandi',
        ]);
    $payRes->assertStatus(201)
        ->assertJson(['success' => true]);

    expect(SalaryPayment::where('worker_id', $this->worker->id)->where('amount', 200000)->exists())->toBeTrue();
});
