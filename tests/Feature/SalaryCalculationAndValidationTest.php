<?php

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDay;
use App\Models\Branch\BranchHoliday;
use App\Models\Day;
use App\Models\Firm\Firm;
use App\Models\Firm\FirmHoliday;
use App\Models\Salary\Salary;
use App\Models\Salary\SalaryBranchDay;
use App\Models\Salary\SalaryBranchHoliday;
use App\Models\Salary\SalaryFirmHoliday;
use App\Models\Salary\SalaryWorkerDay;
use App\Models\Salary\SalaryWorkerHoliday;
use App\Models\User\User;
use App\Models\User\UserFirm;
use App\Models\Worker\Worker;
use App\Models\Worker\WorkerDay;
use App\Models\Worker\WorkerHoliday;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->firm = Firm::create([
        'name' => 'Salary Firm',
        'branch_limit' => 5,
        'branch_price' => 100000,
        'valid_date' => now()->addYear(),
    ]);

    UserFirm::create([
        'user_id' => $this->user->id,
        'firm_id' => $this->firm->id,
    ]);

    $this->branch = Branch::create([
        'name' => 'Salary Branch',
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
        'name' => 'Test Worker',
        'branch_id' => $this->branch->id,
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'hour_price' => 50000,
        'fine_price' => 10000,
    ]);
});

test('StoreSalaryRequest rejects overlapping salary periods for the same worker', function () {
    // Existing salary for 2026-03-01 to 2026-03-15
    Salary::create([
        'worker_id' => $this->worker->id,
        'user_id' => $this->user->id,
        'amount' => 500000,
        'worked_minute' => 600,
        'break_minute' => 0,
        'hour_price' => 50000,
        'from' => '2026-03-01',
        'to' => '2026-03-15',
        'comment' => 'Existing',
    ]);

    // Overlapping salary 1: 2026-03-10 to 2026-03-20
    $response = $this->actingAs($this->user)->postJson('/salary', [
        'worker_id' => $this->worker->id,
        'amount' => 500000,
        'worked_minute' => 600,
        'break_minute' => 0,
        'hour_price' => 50000,
        'from' => '2026-03-10',
        'to' => '2026-03-20',
        'comment' => 'Overlap test',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['from']);

    // Overlapping salary 2: starting on exact previous 'to' date (2026-03-15) must also be rejected
    $responseBoundary = $this->actingAs($this->user)->postJson('/salary', [
        'worker_id' => $this->worker->id,
        'amount' => 500000,
        'worked_minute' => 600,
        'break_minute' => 0,
        'hour_price' => 50000,
        'from' => '2026-03-15',
        'to' => '2026-03-25',
        'comment' => 'Exact boundary overlap test',
    ]);

    $responseBoundary->assertStatus(422)
        ->assertJsonValidationErrors(['from']);

    // Non-overlapping period: starting from previous 'to' + 1 day (2026-03-16 to 2026-03-31) must be allowed
    $responseSuccess = $this->actingAs($this->user)->post('/salary', [
        'worker_id' => $this->worker->id,
        'amount' => 500000,
        'worked_minute' => 600,
        'break_minute' => 0,
        'hour_price' => 50000,
        'from' => '2026-03-16',
        'to' => '2026-03-31',
        'comment' => 'Valid period',
    ]);

    $responseSuccess->assertSessionHasNoErrors();
});

test('StoreSalaryRequest requires comment if salary amount is manually changed from expected', function () {
    // expected amount for 600 minutes at 50,000/hr = 500,000
    // Try sending 700,000 without comment
    $response = $this->actingAs($this->user)->postJson('/salary', [
        'worker_id' => $this->worker->id,
        'amount' => 700000,
        'worked_minute' => 600,
        'break_minute' => 0,
        'hour_price' => 50000,
        'from' => '2026-04-01',
        'to' => '2026-04-15',
        'comment' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['comment']);

    // With comment, it is accepted
    $responseWithComment = $this->actingAs($this->user)->post('/salary', [
        'worker_id' => $this->worker->id,
        'amount' => 700000,
        'worked_minute' => 600,
        'break_minute' => 0,
        'hour_price' => 50000,
        'from' => '2026-04-01',
        'to' => '2026-04-15',
        'comment' => 'Bonus berildi',
    ]);

    $responseWithComment->assertSessionHasNoErrors();
});

test('Salary creation snapshots worker days, worker holidays and filters holidays by period', function () {
    // 1. Create Firm holidays: one in period, one outside
    FirmHoliday::create([
        'firm_id' => $this->firm->id,
        'name' => 'In Period Firm Holiday',
        'date' => '2026-05-05',
    ]);
    FirmHoliday::create([
        'firm_id' => $this->firm->id,
        'name' => 'Out of Period Firm Holiday',
        'date' => '2026-06-01',
    ]);

    // 2. Create Branch holidays: one in period, one outside
    BranchHoliday::create([
        'branch_id' => $this->branch->id,
        'name' => 'In Period Branch Holiday',
        'date' => '2026-05-10',
    ]);
    BranchHoliday::create([
        'branch_id' => $this->branch->id,
        'name' => 'Out of Period Branch Holiday',
        'date' => '2026-06-10',
    ]);

    // 3. Create worker holiday: one in period, one outside
    WorkerHoliday::create([
        'user_id' => $this->user->id,
        'worker_id' => $this->worker->id,
        'from' => '2026-05-02',
        'to' => '2026-05-03',
        'comment' => 'In period leave',
    ]);
    WorkerHoliday::create([
        'user_id' => $this->user->id,
        'worker_id' => $this->worker->id,
        'from' => '2026-06-15',
        'to' => '2026-06-20',
        'comment' => 'Out of period leave',
    ]);

    // 4. Create worker days
    WorkerDay::create([
        'worker_id' => $this->worker->id,
        'day_id' => 2, // Monday
    ]);

    // Store salary for 2026-05-01 to 2026-05-31
    $response = $this->actingAs($this->user)->post('/salary', [
        'worker_id' => $this->worker->id,
        'amount' => 500000,
        'worked_minute' => 600,
        'break_minute' => 0,
        'hour_price' => 50000,
        'from' => '2026-05-01',
        'to' => '2026-05-31',
        'comment' => null,
    ]);

    $response->assertSessionHasNoErrors();

    $salary = Salary::where('worker_id', $this->worker->id)->where('from', '2026-05-01')->firstOrFail();

    // Check snapshotted firm holidays (only the one in period)
    $firmHolidays = SalaryFirmHoliday::where('salary_id', $salary->id)->get();
    expect($firmHolidays)->toHaveCount(1);
    expect($firmHolidays->first()->name)->toBe('In Period Firm Holiday');

    // Check snapshotted branch holidays (only the one in period)
    $branchHolidays = SalaryBranchHoliday::where('salary_id', $salary->id)->get();
    expect($branchHolidays)->toHaveCount(1);
    expect($branchHolidays->first()->name)->toBe('In Period Branch Holiday');

    // Check snapshotted worker holidays (only the one in period)
    $workerHolidays = SalaryWorkerHoliday::where('salary_id', $salary->id)->get();
    expect($workerHolidays)->toHaveCount(1);
    expect($workerHolidays->first()->comment)->toBe('In period leave');

    // Check snapshotted worker days
    $workerDays = SalaryWorkerDay::where('salary_id', $salary->id)->get();
    expect($workerDays)->toHaveCount(1);
    expect($workerDays->first()->day_id)->toBe(2);
});

test('WorkerPortalApiController submitRequest does not auto-create WorkerHoliday', function () {
    $token = $this->worker->createToken('worker-token', ['worker'])->plainTextToken;

    $initialCount = WorkerHoliday::where('worker_id', $this->worker->id)->count();

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson('/api/worker/portal/requests', [
            'type' => 'day_off',
            'date' => '2026-07-01',
            'comment' => 'Family event day off request',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    // Verify NO WorkerHoliday was created
    expect(WorkerHoliday::where('worker_id', $this->worker->id)->count())->toBe($initialCount);
});
