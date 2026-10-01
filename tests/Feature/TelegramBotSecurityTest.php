<?php

use App\Models\Branch\Branch;
use App\Models\Firm\Firm;
use App\Models\Worker\Worker;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->firm = Firm::create([
        'name' => 'Telegram Test Firm',
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
        'latitude' => 41.2995,
        'longitude' => 69.2401,
        'status' => 1,
    ]);

    $this->worker = Worker::create([
        'branch_id' => $this->branch->id,
        'name' => 'Bot Worker',
        'telegram_id' => 987654321,
        'employeeNoString' => '3001',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'hour_price' => 20000,
        'fine_price' => 1000,
        'phone' => '998909876543',
        'address' => 'Tashkent',
        'status' => 1,
    ]);
});

function generateValidInitData(int $telegramId, string $botToken, ?int $authDate = null): string
{
    $authDate = $authDate ?? time();
    $params = [
        'auth_date' => (string) $authDate,
        'query_id' => 'AAHdF6IQAAAAAN0XohD123',
        'user' => json_encode(['id' => $telegramId, 'first_name' => 'Test', 'username' => 'testuser']),
    ];

    ksort($params);
    $dataCheckArr = [];
    foreach ($params as $k => $v) {
        $dataCheckArr[] = "{$k}={$v}";
    }
    $dataCheckString = implode("\n", $dataCheckArr);

    $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

    $params['hash'] = $hash;
    return http_build_query($params);
}

test('telegram authenticate succeeds with valid initData and does not leak sensitive worker fields', function () {
    $botToken = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';
    Config::set('services.telegram.bot_token', $botToken);

    $initData = generateValidInitData(987654321, $botToken);

    $response = $this->postJson('/api/bot/auth', [
        'initData' => $initData,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'worker' => [
                'id' => $this->worker->id,
                'name' => 'Bot Worker',
                'telegram_id' => '987654321',
                'employeeNoString' => (string) $this->worker->id,
            ],
        ]);

    // Ensure sensitive fields like password or hour_price are NOT exposed
    $workerData = $response->json('worker');
    expect(array_key_exists('password', $workerData))->toBeFalse()
        ->and(array_key_exists('hour_price', $workerData))->toBeFalse()
        ->and(array_key_exists('fine_price', $workerData))->toBeFalse();
});

test('telegram authenticate rejects invalid, missing, or forged initData', function () {
    $botToken = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';
    Config::set('services.telegram.bot_token', $botToken);

    // 1. Missing initData
    $this->postJson('/api/bot/auth', [])
        ->assertStatus(401);

    // 2. Tampered telegram_id with mismatched hash
    $validInitData = generateValidInitData(987654321, $botToken);
    $tampered = str_replace('987654321', '111111111', $validInitData);
    $this->postJson('/api/bot/auth', ['initData' => $tampered])
        ->assertStatus(401);

    // 3. Expired initData (> 24h old)
    $expiredInitData = generateValidInitData(987654321, $botToken, time() - 90000);
    $this->postJson('/api/bot/auth', ['initData' => $expiredInitData])
        ->assertStatus(401);
});

test('telegram webhook checks X-Telegram-Bot-Api-Secret-Token when secret is configured', function () {
    // 1. If secret is configured
    Config::set('services.telegram.webhook_secret', 'my_webhook_secret_token');

    // Missing header -> 401
    $this->post('/telegram/handle', [])
        ->assertStatus(401);

    // Invalid header -> 401
    $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'wrong_token'])
        ->post('/telegram/handle', [])
        ->assertStatus(401);

    // Valid header -> 200
    $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'my_webhook_secret_token'])
        ->post('/telegram/handle', [])
        ->assertStatus(200);

    // 2. If secret is empty (backward compatibility)
    Config::set('services.telegram.webhook_secret', null);
    $this->post('/telegram/handle', [])
        ->assertStatus(200);
});

test('telegram:set-webhook command sends secret token to telegram api', function () {
    Config::set('services.telegram.bot_token', 'fake_bot_token');
    Config::set('services.telegram.webhook_secret', 'secret123');
    Config::set('app.url', 'https://panel.payday.uz');

    Http::fake([
        'https://api.telegram.org/botfake_bot_token/setWebhook' => Http::response([
            'ok' => true,
            'result' => true,
            'description' => 'Webhook was set',
        ], 200),
    ]);

    $this->artisan('telegram:set-webhook')
        ->assertExitCode(0)
        ->expectsOutputToContain('Webhook was set');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.telegram.org/botfake_bot_token/setWebhook'
            && $request['url'] === 'https://panel.payday.uz/telegram/handle'
            && $request['secret_token'] === 'secret123';
    });
});
