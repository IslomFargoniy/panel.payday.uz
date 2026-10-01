<?php

use App\Console\Commands\HikvisionHealthcheckCommand;
use App\Models\Branch\Branch;
use App\Models\Branch\BranchDevice;
use App\Models\Firm\Firm;
use App\Models\User\User;
use App\Models\Worker\Worker;
use App\Services\Hikvision\GatewayClient;
use App\Services\Hikvision\HikvisionSyncService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->firm = Firm::create([
        'name' => 'Gateway Firm',
        'branch_limit' => 5,
        'branch_price' => 100000,
        'valid_date' => now()->addYear(),
    ]);
    $this->branch = Branch::create([
        'name' => 'Gateway Branch',
        'firm_id' => $this->firm->id,
        'work_time' => '09:00',
        'end_time' => '18:00',
    ]);
});

test('GatewayClient sends X-Gateway-Token header when token is configured', function () {
    Config::set('hikvision.gateway_token', 'secret-gateway-token-123');
    Config::set('hikvision.gateway_url', 'http://127.0.0.1:7661');

    Http::fake([
        'http://127.0.0.1:7661/health' => Http::response(['status' => 'ok'], 200),
    ]);

    GatewayClient::http()->get('/health');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://127.0.0.1:7661/health'
            && $request->hasHeader('X-Gateway-Token', 'secret-gateway-token-123');
    });
});

test('GatewayClient does NOT send X-Gateway-Token header when token is empty', function () {
    Config::set('hikvision.gateway_token', '');
    Config::set('hikvision.gateway_url', 'http://127.0.0.1:7661');

    Http::fake([
        'http://127.0.0.1:7661/health' => Http::response(['status' => 'ok'], 200),
    ]);

    GatewayClient::http()->get('/health');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://127.0.0.1:7661/health'
            && !$request->hasHeader('X-Gateway-Token');
    });
});

test('HikvisionSyncService sends X-Gateway-Token header on ISUP requests', function () {
    Config::set('hikvision.gateway_token', 'sync-secret-token-456');
    Config::set('hikvision.gateway_url', 'http://127.0.0.1:7661');

    Http::fake([
        'http://127.0.0.1:7661/api/isapi' => Http::response(['statusCode' => 1], 200),
    ]);

    $device = BranchDevice::create([
        'branch_id' => $this->branch->id,
        'device_id' => 'ISUP_DEV_01',
        'mac_address' => '00:11:22:33:44:55',
        'connection_type' => 'isup',
        'status' => true,
    ]);

    $worker = Worker::factory()->create([
        'branch_id' => $this->branch->id,
        'employeeNoString' => '10001',
    ]);

    $service = app(HikvisionSyncService::class);
    $service->syncWorkerToDevice($worker, $device);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://127.0.0.1:7661/api/isapi'
            && $request->hasHeader('X-Gateway-Token', 'sync-secret-token-456');
    });
});

test('HikvisionHealthcheckCommand sends X-Gateway-Token header', function () {
    Config::set('hikvision.gateway_token', 'health-secret-token-789');
    Config::set('hikvision.gateway_url', 'http://127.0.0.1:7661');

    Http::fake([
        'http://127.0.0.1:7661/health' => Http::response([
            'status' => 'ok',
            'connected_devices_count' => 1,
        ], 200),
        'http://127.0.0.1:7661/api/devices' => Http::response([
            ['device_id' => 'DEV_01', 'online' => true],
        ], 200),
    ]);

    $this->artisan('hikvision:healthcheck')->assertExitCode(0);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://127.0.0.1:7661/health'
            && $request->hasHeader('X-Gateway-Token', 'health-secret-token-789');
    });

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://127.0.0.1:7661/api/devices'
            && $request->hasHeader('X-Gateway-Token', 'health-secret-token-789');
    });
});
