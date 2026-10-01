<?php

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDevice;
use App\Models\Firm\Firm;
use App\Models\Salary\Salary;
use App\Models\Salary\SalaryPayment;
use App\Models\User\User;
use App\Models\User\UserFirm;
use App\Models\Worker\Worker;
use App\Jobs\SyncWorkerToHikvisionJob;
use App\Services\Attendance\AttendanceReportService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->firm = Firm::create([
        'name' => 'Reliability Firm',
        'branch_limit' => 5,
        'branch_price' => 100000,
        'valid_date' => now()->addYear(),
    ]);
    UserFirm::create([
        'user_id' => $this->user->id,
        'firm_id' => $this->firm->id,
    ]);
    $this->branch = Branch::create([
        'name' => 'Reliability Branch',
        'firm_id' => $this->firm->id,
        'work_time' => '09:00',
        'end_time' => '18:00',
    ]);
});

test('Worker::getWorkerBalance correctly computes balance using Eloquent sums', function () {
    $worker = Worker::factory()->create([
        'branch_id' => $this->branch->id,
    ]);

    Salary::factory()->create([
        'user_id' => $this->user->id,
        'worker_id' => $worker->id,
        'amount' => 5000000,
    ]);

    SalaryPayment::factory()->create([
        'user_id' => $this->user->id,
        'worker_id' => $worker->id,
        'amount' => 2000000,
    ]);

    $balance = Worker::getWorkerBalance($worker->id);
    expect($balance)->toBe(3000000.0);
});

test('WorkerObserver prevents deleting worker with non-zero balance', function () {
    $worker = Worker::factory()->create([
        'branch_id' => $this->branch->id,
    ]);

    Salary::factory()->create([
        'user_id' => $this->user->id,
        'worker_id' => $worker->id,
        'amount' => 150000,
    ]);

    expect(fn () => $worker->delete())
        ->toThrow(ValidationException::class);
});

test('Worker soft delete works and preserves worker in database', function () {
    $worker = Worker::factory()->create([
        'branch_id' => $this->branch->id,
    ]);

    $worker->delete();

    expect(Worker::count())->toBe(0)
        ->and(Worker::withTrashed()->count())->toBe(1);
});

test('Worker force delete is blocked if worker has salary or payment history', function () {
    $worker = Worker::factory()->create([
        'branch_id' => $this->branch->id,
    ]);

    Salary::factory()->create([
        'user_id' => $this->user->id,
        'worker_id' => $worker->id,
        'amount' => 1000000,
    ]);

    SalaryPayment::factory()->create([
        'user_id' => $this->user->id,
        'worker_id' => $worker->id,
        'amount' => 1000000,
    ]);

    // Balance is 0, so soft delete succeeds
    $worker->delete();

    // Force delete should be blocked due to salary/payment history
    expect(fn () => $worker->forceDelete())
        ->toThrow(ValidationException::class);
});

test('Worker updates dispatch SyncWorkerToHikvisionJob', function () {
    Queue::fake();

    $worker = Worker::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Original Name',
    ]);

    $worker->update(['name' => 'Updated Name']);

    Queue::assertPushed(SyncWorkerToHikvisionJob::class, function ($job) use ($worker) {
        return $job->workerId === $worker->id;
    });
});

test('AttendanceReportService rejects invalid month format', function () {
    $service = app(AttendanceReportService::class);
    $request = \Illuminate\Http\Request::create('/attendance-grid', 'GET', ['month' => 'invalid-month']);

    expect(fn () => $service->getAttendanceGridData($request))
        ->toThrow(ValidationException::class);
});

test('BranchDevice store request prevents duplicate active device_id', function () {
    BranchDevice::create([
        'branch_id' => $this->branch->id,
        'mac_address' => 'AA:BB:CC:DD:EE:01',
        'device_id' => 'DEVICE_001',
        'status' => true,
    ]);

    $response = $this->actingAs($this->user)->post(route('branch_device.store'), [
        'branch_id' => $this->branch->id,
        'mac_address' => 'AA:BB:CC:DD:EE:02',
        'device_id' => 'DEVICE_001',
    ]);

    $response->assertSessionHasErrors(['device_id']);
});
