<?php

namespace Tests\Feature;

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDevice;
use App\Models\Branch\BranchHoliday;
use App\Models\Firm\Firm;
use App\Models\Salary\SalaryPayment;
use App\Models\User\User;
use App\Models\Worker\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FormValidationAndEmptyFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Admin']);
    }

    public function test_worker_can_be_created_with_empty_optional_fields(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => uniqid('admin_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('Admin');

        $firm = Firm::create([
            'name' => 'Test Firm',
            'branch_limit' => 10,
            'branch_price' => 1000,
            'valid_date' => now()->addYear()->toDateString(),
        ]);

        $branch = Branch::create([
            'firm_id' => $firm->id,
            'name' => 'Main Branch',
            'work_time' => '09:00',
            'end_time' => '18:00',
            'hour_price' => 10000,
            'fine_price' => 5000,
        ]);

        // Submit form with empty strings for phone, address, comment, hour_price, fine_price
        $response = $this->actingAs($admin)->post('/worker', [
            'branch_id' => $branch->id,
            'name' => 'John Doe',
            'work_time' => '09:00',
            'end_time' => '18:00',
            'phone' => '',
            'address' => '',
            'comment' => '',
            'hour_price' => '',
            'fine_price' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workers', [
            'name' => 'John Doe',
            'phone' => null,
            'address' => null,
            'comment' => null,
            'hour_price' => 0,
            'fine_price' => 0,
        ]);

        // Submit a SECOND worker with empty phone to ensure NO unique constraint collision on ''
        $response2 = $this->actingAs($admin)->post('/worker', [
            'branch_id' => $branch->id,
            'name' => 'Jane Smith',
            'work_time' => '09:00',
            'end_time' => '18:00',
            'phone' => '',
            'address' => '',
            'comment' => '',
            'hour_price' => 15000,
            'fine_price' => 0,
        ]);

        $response2->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workers', [
            'name' => 'Jane Smith',
            'phone' => null,
        ]);
    }

    public function test_branch_can_be_created_with_empty_optional_fields(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => uniqid('admin_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('Admin');

        $firm = Firm::create([
            'name' => 'Test Firm 2',
            'branch_limit' => 10,
            'branch_price' => 1000,
            'valid_date' => now()->addYear()->toDateString(),
        ]);

        $response = $this->actingAs($admin)->post('/branch', [
            'firm_id' => $firm->id,
            'name' => 'Second Branch',
            'work_time' => '09:00',
            'end_time' => '18:00',
            'address' => '',
            'comment' => '',
            'hour_price' => '',
            'fine_price' => '',
            'telegram_group_id' => '',
            'latitude' => '',
            'longitude' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branches', [
            'name' => 'Second Branch',
            'address' => null,
            'comment' => null,
            'hour_price' => 0,
            'fine_price' => 0,
            'telegram_group_id' => null,
        ]);
    }

    public function test_branch_device_can_be_created_with_empty_optional_fields(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => uniqid('admin_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('Admin');

        $firm = Firm::create([
            'name' => 'Test Firm 3',
            'branch_limit' => 10,
            'branch_price' => 1000,
            'valid_date' => now()->addYear()->toDateString(),
        ]);

        $branch = Branch::create([
            'firm_id' => $firm->id,
            'name' => 'Device Branch',
            'work_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $response = $this->actingAs($admin)->post('/branch_device', [
            'branch_id' => $branch->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'name' => '',
            'device_id' => '',
            'encryption_key' => '',
            'connection_type' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branch_devices', [
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'name' => null,
            'device_id' => null,
            'connection_type' => 'http_listening',
        ]);
    }

    public function test_branch_holiday_can_be_created_with_empty_optional_comment(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => uniqid('admin_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('Admin');

        $firm = Firm::create([
            'name' => 'Test Firm 4',
            'branch_limit' => 10,
            'branch_price' => 1000,
            'valid_date' => now()->addYear()->toDateString(),
        ]);

        $branch = Branch::create([
            'firm_id' => $firm->id,
            'name' => 'Holiday Branch',
            'work_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $response = $this->actingAs($admin)->post('/branch_holiday', [
            'branch_id' => $branch->id,
            'name' => 'Navruz',
            'date' => '2026-03-21',
            'comment' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branch_holidays', [
            'name' => 'Navruz',
            'comment' => null,
        ]);
    }

    public function test_salary_payment_can_be_created_with_empty_comment(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => uniqid('admin_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('Admin');

        $firm = Firm::create([
            'name' => 'Test Firm 5',
            'branch_limit' => 10,
            'branch_price' => 1000,
            'valid_date' => now()->addYear()->toDateString(),
        ]);

        $branch = Branch::create([
            'firm_id' => $firm->id,
            'name' => 'Salary Branch',
            'work_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $worker = Worker::create([
            'branch_id' => $branch->id,
            'name' => 'Paid Worker',
            'work_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $response = $this->actingAs($admin)->post('/salary_payment', [
            'worker_id' => $worker->id,
            'amount' => 500000,
            'comment' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('salary_payments', [
            'worker_id' => $worker->id,
            'amount' => 500000,
            'comment' => null,
        ]);
    }

    public function test_worker_can_be_updated_with_empty_optional_fields(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => uniqid('admin_') . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('Admin');

        $firm = Firm::create([
            'name' => 'Test Firm 6',
            'branch_limit' => 10,
            'branch_price' => 1000,
            'valid_date' => now()->addYear()->toDateString(),
        ]);

        $branch = Branch::create([
            'firm_id' => $firm->id,
            'name' => 'Update Branch',
            'work_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $worker = Worker::create([
            'branch_id' => $branch->id,
            'name' => 'Initial Worker',
            'work_time' => '09:00',
            'end_time' => '18:00',
            'phone' => '+998901234567',
            'address' => 'Old Address',
            'comment' => 'Old Comment',
            'hour_price' => 20000,
            'fine_price' => 5000,
        ]);

        // Update worker to clear phone, address, comment, hour_price
        $response = $this->actingAs($admin)->post("/worker/{$worker->id}", [
            '_method' => 'put',
            'name' => 'Updated Worker Name',
            'work_time' => '08:30',
            'end_time' => '17:30',
            'phone' => '',
            'address' => '',
            'comment' => '',
            'hour_price' => '',
            'fine_price' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $worker->refresh();
        $this->assertEquals('Updated Worker Name', $worker->name);
        $this->assertNull($worker->phone);
        $this->assertNull($worker->address);
        $this->assertNull($worker->comment);
        $this->assertEquals(0, $worker->hour_price);
        $this->assertEquals(0, $worker->fine_price);
    }
}
