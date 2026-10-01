<?php

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDevice;
use App\Models\Firm\Firm;
use App\Models\Hikvision\HikvisionAccess;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\User\User;
use App\Models\Worker\Worker;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->firm = Firm::create([
        'name' => 'Test Firm',
        'status' => 1,
        'valid_date' => now()->addYear()->toDateString(),
        'branch_limit' => 5,
        'branch_price' => 0,
    ]);

    $this->branch = Branch::create([
        'firm_id' => $this->firm->id,
        'name' => 'Branch 1',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'hour_price' => 20000,
        'fine_price' => 1000,
        'status' => 1,
    ]);

    $this->device = BranchDevice::create([
        'branch_id' => $this->branch->id,
        'name' => 'Device 1',
        'mac_address' => '11:22:33:44:55:66',
        'device_id' => 'DEV001',
        'connection_type' => 'http_listening',
        'status' => 1,
    ]);

    $this->worker = Worker::create([
        'branch_id' => $this->branch->id,
        'name' => 'Test Worker',
        'employeeNoString' => '2001',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'hour_price' => 20000,
        'fine_price' => 1000,
        'phone' => '998901234567',
        'address' => 'Tashkent',
        'status' => 1,
    ]);
});

test('hikvision callback generates safe filename and prevents path traversal in picture string', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('malicious_name_../../../etc/passwd.jpg');

    $response = $this->post('/api/hikvision-callback', [
        'macAddress' => '11:22:33:44:55:66',
        'AccessControllerEvent' => [
            'employeeNoString' => '2001',
            'attendanceStatus' => 'checkIn',
        ],
        'Picture' => $file,
    ]);

    $response->assertStatus(200);

    // Verify stored file does not contain path traversal characters
    $allFiles = Storage::disk('public')->allFiles();
    expect($allFiles)->toHaveCount(1);
    expect($allFiles[0])->not->toContain('passwd')
        ->and($allFiles[0])->not->toContain('..');
});

test('hikvision access event destroy only deletes files safely inside hikvision storage directory', function () {
    Storage::fake('public');

    // Create a dummy file inside hikvision/DEV001/test.jpg
    Storage::disk('public')->put('hikvision/DEV001/test.jpg', 'fake image content');

    $access = HikvisionAccess::create([
        'ipAddress' => '127.0.0.1',
        'portNo' => 80,
        'protocol' => 'HTTP',
        'macAddress' => '11:22:33:44:55:66',
        'channelId' => 1,
        'dateTime' => now(),
        'activePostCount' => 1,
        'eventType' => 'event',
        'eventDescription' => 'desc',
        'shortSerialNumber' => 'DEV001',
    ]);

    $event = HikvisionAccessEvent::create([
        'hikvision_access_id' => $access->id,
        'worker_id' => $this->worker->id,
        'serialNo' => 'DEV001',
        'type' => 1,
        'majorEventType' => 1,
        'subEventType' => 1,
        'name' => 'Test Worker',
        'cardReaderKind' => 1,
        'cardReaderNo' => 1,
        'verifyNo' => 1,
        'employeeNoString' => '2001',
        'currentVerifyMode' => 'card',
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
        'status' => 1,
        'mask' => 'no',
        'picture' => 'test.jpg',
    ]);

    $this->actingAs($this->admin);
    $response = $this->delete(route('hikvision_access_event.destroy', $event->id));
    $response->assertRedirect();

    expect(Storage::disk('public')->exists('hikvision/DEV001/test.jpg'))->toBeFalse();
});
