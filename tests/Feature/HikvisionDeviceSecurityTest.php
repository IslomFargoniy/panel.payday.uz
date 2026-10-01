<?php

use App\Models\Branch\Branch;
use App\Models\Branch\BranchDevice;
use App\Models\Firm\Firm;
use App\Models\Worker\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->firm = Firm::create([
        'name' => 'Prod Firm',
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
    ]);
});

test('gateway token middleware operates in soft mode by default', function () {
    Config::set('hikvision.gateway_token', 'secret123');
    Config::set('hikvision.gateway_token_required', false);

    $device = BranchDevice::create([
        'branch_id' => $this->branch->id,
        'name' => 'Test ISUP',
        'mac_address' => '00:11:22:33:44:01',
        'device_id' => 'branch14',
        'connection_type' => 'isup',
        'status' => 1,
        'encryption_key' => 'customKey123',
    ]);

    // Request without token should succeed in soft mode (required = false)
    $response = $this->getJson('/api/hikvision-device-key?device_id=branch14');
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'device_id' => 'branch14',
            'encryption_key' => 'customKey123',
        ]);
});

test('gateway token middleware rejects invalid token when required is true', function () {
    Config::set('hikvision.gateway_token', 'secret123');
    Config::set('hikvision.gateway_token_required', true);

    BranchDevice::create([
        'branch_id' => $this->branch->id,
        'name' => 'Test ISUP',
        'mac_address' => '00:11:22:33:44:02',
        'device_id' => 'branch14',
        'connection_type' => 'isup',
        'status' => 1,
        'encryption_key' => 'customKey123',
    ]);

    // Missing token -> 401
    $this->getJson('/api/hikvision-device-key?device_id=branch14')
        ->assertStatus(401);

    // Wrong token -> 401
    $this->withHeaders(['X-Gateway-Token' => 'wrong_token'])
        ->getJson('/api/hikvision-device-key?device_id=branch14')
        ->assertStatus(401);

    // Correct token -> 200
    $this->withHeaders(['X-Gateway-Token' => 'secret123'])
        ->getJson('/api/hikvision-device-key?device_id=branch14')
        ->assertStatus(200)
        ->assertJson(['encryption_key' => 'customKey123']);
});

test('getDeviceKey returns 404 when device is not found or key is empty', function () {
    $this->getJson('/api/hikvision-device-key?device_id=non_existent')
        ->assertStatus(404);

    BranchDevice::create([
        'branch_id' => $this->branch->id,
        'name' => 'Empty Key Device',
        'mac_address' => '00:11:22:33:44:03',
        'device_id' => 'empty_device',
        'connection_type' => 'isup',
        'status' => 1,
        'encryption_key' => '',
    ]);

    $this->getJson('/api/hikvision-device-key?device_id=empty_device')
        ->assertStatus(404);
});

test('updateDeviceStatus does not overwrite inactive device status', function () {
    $device = BranchDevice::create([
        'branch_id' => $this->branch->id,
        'name' => 'Disabled Device',
        'mac_address' => '00:11:22:33:44:04',
        'device_id' => 'disabled_dev',
        'connection_type' => 'isup',
        'status' => 0, // Disabled device
        'is_online' => false,
    ]);

    $this->postJson('/api/hikvision-device-status', [
        'device_id' => 'disabled_dev',
        'status' => 'online',
    ])->assertStatus(200);

    $device->refresh();
    expect($device->is_online)->toBeTrue()
        ->and($device->status)->toBeFalse(); // Must remain inactive
});

test('hikvision-callback accepts events from all 4 active production device identifiers with normalized MACs', function () {
    // Create the 4 production-like devices
    $dev15 = BranchDevice::create([
        'branch_id' => $this->branch->id,
        'name' => 'Device 15',
        'mac_address' => 'e0:ca:3c:f9:ae:fd',
        'device_id' => 'AE7106709',
        'connection_type' => 'http_listening',
        'status' => 1,
    ]);

    $dev17 = BranchDevice::create([
        'branch_id' => $this->branch->id,
        'name' => 'Device 17',
        'mac_address' => '88:de:39:3e:ac:3c',
        'device_id' => 'GG8507033',
        'connection_type' => 'http_listening',
        'status' => 1,
    ]);

    $dev18 = BranchDevice::create([
        'branch_id' => $this->branch->id,
        'name' => 'Device 18',
        'mac_address' => '88:de:39:32:a9:dc',
        'device_id' => 'GF0132950',
        'connection_type' => 'http_listening',
        'status' => 1,
    ]);

    $dev20 = BranchDevice::create([
        'branch_id' => $this->branch->id,
        'name' => 'Device 20 ISUP',
        'mac_address' => '00:11:22:33:44:14',
        'device_id' => 'branch14',
        'connection_type' => 'isup',
        'status' => 1,
        'encryption_key' => 'KeyBranch14',
    ]);

    $worker = Worker::create([
        'branch_id' => $this->branch->id,
        'name' => 'Active Worker',
        'employeeNoString' => '1001',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'hour_price' => 20000,
        'fine_price' => 1000,
        'phone' => '998901112233',
        'address' => 'Tashkent',
        'status' => 1,
    ]);

    // 1. Test Device 15 with uppercase dash-delimited MAC
    $res15 = $this->postJson('/api/hikvision-callback', [
        'macAddress' => 'E0-CA-3C-F9-AE-FD',
        'dateTime' => now()->toIso8601String(),
        'AccessControllerEvent' => [
            'employeeNoString' => '1001',
            'attendanceStatus' => 'checkIn',
            'serialNo' => 'AE7106709',
        ],
    ]);
    $res15->assertStatus(200);

    // 2. Test Device 17 with shortSerialNumber
    $res17 = $this->postJson('/api/hikvision-callback', [
        'shortSerialNumber' => 'GG8507033',
        'dateTime' => now()->toIso8601String(),
        'AccessControllerEvent' => [
            'employeeNoString' => '1001',
            'attendanceStatus' => 'checkOut',
        ],
    ]);
    $res17->assertStatus(200);

    // 3. Test Device 18 with colon-delimited MAC
    $res18 = $this->postJson('/api/hikvision-callback', [
        'macAddress' => '88:de:39:32:a9:dc',
        'dateTime' => now()->toIso8601String(),
        'AccessControllerEvent' => [
            'employeeNoString' => '1001',
            'attendanceStatus' => 'checkIn',
        ],
    ]);
    $res18->assertStatus(200);

    // 4. Test Device 20 ISUP with device_id
    $res20 = $this->postJson('/api/hikvision-callback', [
        'device_id' => 'branch14',
        'dateTime' => now()->toIso8601String(),
        'AccessControllerEvent' => [
            'employeeNoString' => '1001',
            'attendanceStatus' => 'checkOut',
        ],
    ]);
    $res20->assertStatus(200);
});

test('hikvision-callback rejects unrecognized or inactive device', function () {
    $res = $this->postJson('/api/hikvision-callback', [
        'macAddress' => 'aa:bb:cc:dd:ee:ff',
        'dateTime' => now()->toIso8601String(),
        'AccessControllerEvent' => [
            'employeeNoString' => '9999',
            'attendanceStatus' => 'checkIn',
        ],
    ]);

    $res->assertStatus(403);
});

test('fill-default-keys command dry-run and force modes work correctly', function () {
    BranchDevice::create([
        'branch_id' => $this->branch->id,
        'name' => 'No Key ISUP',
        'mac_address' => '00:11:22:33:44:99',
        'device_id' => 'isup_no_key',
        'connection_type' => 'isup',
        'status' => 1,
        'encryption_key' => null,
    ]);

    $this->artisan('hikvision:fill-default-keys --dry-run')
        ->assertExitCode(0)
        ->expectsOutputToContain('[DRY-RUN]');

    expect(BranchDevice::where('device_id', 'isup_no_key')->first()->encryption_key)->toBeNull();

    $this->artisan('hikvision:fill-default-keys --force')
        ->assertExitCode(0)
        ->expectsOutputToContain('PayDay142026');

    expect(BranchDevice::where('device_id', 'isup_no_key')->first()->encryption_key)->toBe('PayDay142026');
});
