<?php

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDevice;
use App\Models\Firm\Firm;
use App\Models\Hikvision\HikvisionAccess;
use App\Models\Hikvision\HikvisionAccessEvent;
use App\Models\User\User;
use App\Models\Worker\Worker;
use App\Services\Attendance\AttendancePairingService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->firm = Firm::create([
        'name' => 'Soft Delete Test Firm',
        'status' => 1,
        'valid_date' => now()->addYear()->toDateString(),
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
        'latitude' => 41.2995,
        'longitude' => 69.2401,
    ]);

    $this->device = BranchDevice::create([
        'branch_id' => $this->branch->id,
        'name' => 'Main Terminal',
        'mac_address' => '00:11:22:33:44:55',
        'device_id' => 'DEV001',
        'connection_type' => 'http_listening',
        'status' => 1,
    ]);

    $this->admin = User::factory()->create([
        'email' => 'admin@softdelete.test',
        'password' => bcrypt('AdminSecret123!'),
    ]);
    $this->admin->assignRole('Admin');
});

test('soft-deleted workers are excluded from AttendancePairingService paired events', function () {
    $today = date('Y-m-d');

    $worker = Worker::create([
        'branch_id' => $this->branch->id,
        'name' => 'Active Then Deleted Worker',
        'phone' => '998901111111',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'status' => 1,
    ]);

    $ha = HikvisionAccess::create(['shortSerialNumber' => 'DEV001', 'dateTime' => "{$today} 09:00:00"]);
    $event = new HikvisionAccessEvent([
        'employeeNoString' => (string) $worker->employeeNoString,
        'attendanceStatus' => 'checkIn',
        'label' => 'Keldi',
        'work_time' => '09:00:00',
    ]);
    $event->hikvision_access_id = $ha->id;
    $event->timestamps = false;
    $event->created_at = Carbon::parse("{$today} 09:00:00");
    $event->updated_at = Carbon::parse("{$today} 09:00:00");
    $event->save();

    $pairingService = app(AttendancePairingService::class);
    $req = new Request(['branch_id' => $this->branch->id]);

    // Active worker appears in paired query
    $pairedActive = $pairingService->buildPairedEventsQuery($req, $today, $today)->get();
    expect($pairedActive->count())->toBe(1);

    // Soft delete worker
    $worker->delete();

    // Soft-deleted worker must NOT appear in paired query
    $pairedDeleted = $pairingService->buildPairedEventsQuery($req, $today, $today)->get();
    expect($pairedDeleted->count())->toBe(0);
});

test('phone number of soft-deleted worker can be reused by a new worker', function () {
    $this->actingAs($this->admin);

    $oldWorker = Worker::create([
        'branch_id' => $this->branch->id,
        'name' => 'Old Worker',
        'phone' => '998909998877',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'status' => 1,
    ]);

    // Attempting to create duplicate active worker phone fails validation
    $resFail = $this->postJson('/worker', [
        'branch_id' => $this->branch->id,
        'name' => 'Duplicate Phone Worker',
        'phone' => '998909998877',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);
    $resFail->assertStatus(422)
        ->assertJsonValidationErrors(['phone']);

    // Soft-delete old worker
    $oldWorker->delete();

    // Now creating a new worker with the same phone succeeds
    $resSuccess = $this->postJson('/worker', [
        'branch_id' => $this->branch->id,
        'name' => 'New Worker Reusing Phone',
        'phone' => '998909998877',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);
    $resSuccess->assertStatus(302); // Redirect back on success in web panel

    expect(Worker::where('phone', '998909998877')->count())->toBe(1)
        ->and(Worker::withTrashed()->where('phone', '998909998877')->count())->toBe(2);
});

test('soft-deleted worker cannot login via api auth', function () {
    $worker = Worker::create([
        'branch_id' => $this->branch->id,
        'name' => 'Portal Worker',
        'phone' => '998901234455',
        'password' => 'secretPassword123',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'status' => 1,
    ]);

    // Active worker can log in
    $this->postJson('/api/auth/login', [
        'email' => '998901234455',
        'password' => 'secretPassword123',
    ])->assertStatus(200);

    // Soft delete worker
    $worker->delete();

    // Soft deleted worker cannot log in
    $res = $this->postJson('/api/auth/login', [
        'email' => '998901234455',
        'password' => 'secretPassword123',
    ]);
    $res->assertStatus(401);
});

test('soft-deleted worker cannot authenticate or submit attendance via telegram bot', function () {
    Config::set('services.telegram.bot_token', '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
    $botToken = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';

    $telegramId = 777666555;
    $worker = Worker::create([
        'branch_id' => $this->branch->id,
        'name' => 'Bot Worker',
        'telegram_id' => $telegramId,
        'phone' => '998907776655',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'status' => 1,
    ]);

    // Generate valid initData
    $params = [
        'auth_date' => (string) time(),
        'query_id' => 'AAHdF6IQAAAAAN0XohD123',
        'user' => json_encode(['id' => $telegramId, 'first_name' => 'BotWorker']),
    ];
    ksort($params);
    $dataCheckString = implode("\n", array_map(fn($k, $v) => "{$k}={$v}", array_keys($params), array_values($params)));
    $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $hash = hash_hmac('sha256', $dataCheckString, $secretKey);
    $validInitData = http_build_query(array_merge($params, ['hash' => $hash]));

    // Soft-delete worker
    $worker->delete();

    // Try Telegram authenticate on /api/bot/auth
    $res = $this->postJson('/api/bot/auth', [
        'initData' => $validInitData,
    ]);
    $res->assertStatus(404)
        ->assertJson([
            'success' => false,
        ]);
});

test('hikvision callback does not record events for soft-deleted worker', function () {
    $worker = Worker::create([
        'branch_id' => $this->branch->id,
        'name' => 'Callback Worker',
        'phone' => '998903332211',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'status' => 1,
    ]);

    $worker->delete();

    $res = $this->postJson('/api/hikvision-callback', [
        'macAddress' => '00:11:22:33:44:55',
        'dateTime' => now()->toIso8601String(),
        'AccessControllerEvent' => [
            'employeeNoString' => (string) $worker->employeeNoString,
            'attendanceStatus' => 'checkIn',
        ],
    ]);

    $res->assertStatus(200)
        ->assertJson([
            'success' => false,
        ]);

    expect(HikvisionAccessEvent::where('employeeNoString', (string) $worker->employeeNoString)->count())->toBe(0);
});
