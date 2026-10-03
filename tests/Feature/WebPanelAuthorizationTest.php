<?php

namespace Tests\Feature;

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDay;
use App\Models\Day;
use App\Models\Firm\Firm;
use App\Models\Hikvision\HikvisionAccess;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\User\User;
use App\Models\User\UserFirm;
use App\Models\Worker\Worker;
use App\Models\Worker\WorkerDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPanelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $clientUser;
    protected Firm $myFirm;
    protected Branch $myBranch;
    protected Worker $myWorker;

    protected Firm $otherFirm;
    protected Branch $otherBranch;
    protected Worker $otherWorker;
    protected Day $testDay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->myFirm = Firm::create([
            'name' => 'My Firm',
            'branch_limit' => 10,
            'branch_price' => 100000,
            'valid_date' => now()->addYear(),
        ]);
        $this->myBranch = Branch::create([
            'name' => 'My Branch',
            'firm_id' => $this->myFirm->id,
            'work_time' => '09:00',
            'end_time' => '18:00',
        ]);
        $this->myWorker = Worker::create([
            'name' => 'My Worker',
            'branch_id' => $this->myBranch->id,
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
            'phone' => '998901239999',
            'email' => 'client2@payday.uz',
            'password' => 'secret123',
            'status' => 'approved',
        ]);
        $this->clientUser->assignRole('Client');

        UserFirm::create([
            'user_id' => $this->clientUser->id,
            'firm_id' => $this->myFirm->id,
        ]);

        $this->testDay = Day::create([
            'name' => 'Dushanba',
            'name_ru' => 'Понедельник',
            'name_en' => 'Monday',
            'index' => 2,
        ]);
    }

    public function test_client_cannot_access_daily_attendance_of_other_firm_branch(): void
    {
        $this->actingAs($this->clientUser);

        $response = $this->get("/daily_attendance/{$this->otherBranch->id}");
        $response->assertStatus(403);
    }

    public function test_client_cannot_add_or_delete_worker_day_for_other_firm_worker(): void
    {
        $this->actingAs($this->clientUser);

        // Store worker day for another firm's worker must be forbidden (403)
        $response = $this->post('/worker_day', [
            'worker_id' => $this->otherWorker->id,
            'day_ids' => [$this->testDay->id],
        ]);
        $response->assertStatus(403);

        // Deleting another firm's worker day must be forbidden (403)
        // Temporarily create a worker day directly
        $otherWorkerDay = WorkerDay::withoutEvents(function () {
            return WorkerDay::create([
                'worker_id' => $this->otherWorker->id,
                'day_id' => $this->testDay->id,
            ]);
        });

        $response = $this->delete("/worker_day/{$otherWorkerDay->id}");
        $response->assertStatus(403);
    }

    public function test_client_cannot_delete_hikvision_event_of_other_firm(): void
    {
        $this->actingAs($this->clientUser);

        $access = HikvisionAccess::create([
            'accessControllerEvent' => 'test',
        ]);

        $event = HikvisionAccessEvent::create([
            'hikvision_access_id' => $access->id,
            'employeeNoString' => $this->otherWorker->employeeNoString,
            'attendanceStatus' => 'checkIn',
            'dateTime' => now(),
        ]);

        $response = $this->delete("/hikvision_access_event/{$event->id}");
        $response->assertStatus(403);
    }
}
