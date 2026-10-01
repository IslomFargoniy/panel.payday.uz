<?php

namespace Tests\Feature;

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDevice;
use App\Models\Firm\Firm;
use App\Models\User\User;
use App\Models\User\UserFirm;
use App\Models\Worker\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiIdorProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $clientUser;
    protected Firm $allowedFirm;
    protected Branch $allowedBranch;
    protected Worker $allowedWorker;

    protected Firm $otherFirm;
    protected Branch $otherBranch;
    protected Worker $otherWorker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->allowedFirm = Firm::create([
            'name' => 'Allowed Firm',
            'branch_limit' => 10,
            'branch_price' => 100000,
            'valid_date' => now()->addYear(),
        ]);
        $this->allowedBranch = Branch::create([
            'name' => 'Allowed Branch',
            'firm_id' => $this->allowedFirm->id,
            'work_time' => '09:00',
            'end_time' => '18:00',
        ]);
        $this->allowedWorker = Worker::create([
            'name' => 'Allowed Worker',
            'branch_id' => $this->allowedBranch->id,
            'work_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $this->otherFirm = Firm::create([
            'name' => 'Other Firm',
            'branch_limit' => 10,
            'branch_price' => 100000,
            'valid_date' => now()->addYear(),
        ]);
        $this->otherBranch = Branch::create([
            'name' => 'Other Branch',
            'firm_id' => $this->otherFirm->id,
            'work_time' => '09:00',
            'end_time' => '18:00',
        ]);
        $this->otherWorker = Worker::create([
            'name' => 'Other Worker',
            'branch_id' => $this->otherBranch->id,
            'work_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $this->clientUser = User::create([
            'name' => 'Client User',
            'phone' => '998901112233',
            'email' => 'client@payday.uz',
            'password' => 'secret123',
        ]);
        $this->clientUser->assignRole('Client');

        UserFirm::create([
            'user_id' => $this->clientUser->id,
            'firm_id' => $this->allowedFirm->id,
        ]);
    }

    public function test_client_cannot_access_worker_of_another_firm(): void
    {
        Sanctum::actingAs($this->clientUser, ['panel']);

        // Show
        $response = $this->getJson("/api/workers/{$this->otherWorker->id}");
        $response->assertStatus(403);

        // Update
        $response = $this->putJson("/api/workers/{$this->otherWorker->id}", [
            'name' => 'Hacked Worker',
            'branch_id' => $this->otherBranch->id,
            'work_time' => '09:00',
            'end_time' => '18:00',
        ]);
        $response->assertStatus(403);

        // Destroy
        $response = $this->deleteJson("/api/workers/{$this->otherWorker->id}");
        $response->assertStatus(403);

        // Upload avatar
        Storage::fake('public');
        $file = UploadedFile::fake()->image('avatar.jpg');
        $response = $this->postJson("/api/workers/{$this->otherWorker->id}/avatar", [
            'avatar' => $file,
        ]);
        $response->assertStatus(403);
    }

    public function test_client_can_access_worker_of_own_firm(): void
    {
        Sanctum::actingAs($this->clientUser, ['panel']);

        $response = $this->getJson("/api/workers/{$this->allowedWorker->id}");
        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $this->allowedWorker->id);
    }

    public function test_client_only_sees_devices_of_their_own_firm(): void
    {
        BranchDevice::create([
            'branch_id' => $this->allowedBranch->id,
            'name' => 'Allowed Device',
            'mac_address' => '00:11:22:33:44:55',
            'connection_type' => 'http_listening',
            'status' => 1,
        ]);

        BranchDevice::create([
            'branch_id' => $this->otherBranch->id,
            'name' => 'Other Device',
            'mac_address' => '66:77:88:99:aa:bb',
            'connection_type' => 'http_listening',
            'status' => 1,
        ]);

        Sanctum::actingAs($this->clientUser, ['panel']);

        $response = $this->getJson('/api/devices');
        $response->assertStatus(200);

        $devices = $response->json('data');
        $this->assertCount(1, $devices);
        $this->assertEquals('Allowed Device', $devices[0]['name']);
    }

    public function test_client_cannot_calculate_salary_or_record_payment_for_other_firm_worker(): void
    {
        Sanctum::actingAs($this->clientUser, ['panel']);

        $response = $this->postJson('/api/salary/calculate', [
            'worker_id' => $this->otherWorker->id,
            'amount' => 5000000,
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ]);
        $response->assertStatus(403);

        $response = $this->postJson('/api/salary/payments', [
            'worker_id' => $this->otherWorker->id,
            'amount' => 2000000,
            'date' => '2026-09-25',
        ]);
        $response->assertStatus(403);
    }

    public function test_client_cannot_view_daily_attendance_of_other_firm_branch(): void
    {
        Sanctum::actingAs($this->clientUser, ['panel']);

        $response = $this->getJson("/api/attendance/daily/{$this->otherBranch->id}");
        $response->assertStatus(404);
    }

    public function test_worker_cannot_view_other_workers_in_worker_portal(): void
    {
        $workerToken = $this->allowedWorker->createToken('test-worker', ['worker'])->plainTextToken;

        // Even with worker_id parameter, worker portal must only return current worker's own data
        $response = $this->withHeaders(['Authorization' => "Bearer {$workerToken}"])
            ->getJson("/api/worker/portal/today?worker_id={$this->otherWorker->id}");
        $response->assertStatus(200);
        $response->assertJsonPath('data.worker.id', $this->allowedWorker->id);
    }
}
