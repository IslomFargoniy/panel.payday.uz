<?php

use App\Models\Branch\Branch;
use App\Models\Firm\Firm;
use App\Models\User\User;
use App\Models\Worker\Worker;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->firm = Firm::create([
        'name' => 'Mobile Test Firm',
        'status' => 1,
        'valid_date' => now()->addYear()->toDateString(),
        'branch_limit' => 5,
        'branch_price' => 0,
    ]);

    $this->branch = Branch::create([
        'firm_id' => $this->firm->id,
        'name' => 'Mobile Test Branch',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'hour_price' => 20000,
        'fine_price' => 1000,
        'status' => 1,
    ]);

    $this->user = User::factory()->create([
        'email' => 'admin@payday.uz',
        'password' => Hash::make('Secret123!'),
    ]);
    $this->user->assignRole('Admin');

    $this->worker = Worker::create([
        'branch_id' => $this->branch->id,
        'name' => 'Mobile Worker',
        'phone' => '998901234567',
        'employeeNoString' => '5001',
        'work_time' => '09:00:00',
        'end_time' => '18:00:00',
        'hour_price' => 20000,
        'fine_price' => 1000,
        'status' => 1,
        'password' => 'workerSecret123', // auto-hashed by model cast
    ]);
});

test('worker cannot login with default weak passwords', function () {
    // 1. Weak password '123456'
    $this->postJson('/api/auth/login', [
        'email' => '998901234567',
        'password' => '123456',
    ])->assertStatus(401);

    // 2. Weak password employeeNoString / id
    $this->postJson('/api/auth/login', [
        'email' => '998901234567',
        'password' => (string) $this->worker->id,
    ])->assertStatus(401);

    // 3. Weak password last 4 digits of phone
    $this->postJson('/api/auth/login', [
        'email' => '998901234567',
        'password' => '4567',
    ])->assertStatus(401);
});

test('worker can login with real password and receives token with worker ability', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => '998901234567',
        'password' => 'workerSecret123',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $this->worker->id,
                    'role' => 'Worker',
                ],
            ],
        ]);

    $token = $response->json('data.token');
    expect($token)->not->toBeEmpty();

    // Verify token ability in database
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    expect($personalAccessToken->abilities)->toContain('worker')
        ->and($personalAccessToken->abilities)->not->toContain('panel');
});

test('user login issues token with panel ability', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'admin@payday.uz',
        'password' => 'Secret123!',
    ]);

    $response->assertStatus(200);
    $token = $response->json('data.token');

    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    expect($personalAccessToken->abilities)->toContain('panel')
        ->and($personalAccessToken->abilities)->not->toContain('worker');
});

test('worker token cannot access admin panel api endpoints', function () {
    $workerToken = $this->worker->createToken('test-worker', ['worker'])->plainTextToken;

    // Accessing /api/workers with worker token should be rejected (403)
    $this->withHeaders(['Authorization' => "Bearer {$workerToken}"])
        ->getJson('/api/workers')
        ->assertStatus(403);

    // Accessing /api/dashboard with worker token should be rejected (403)
    $this->withHeaders(['Authorization' => "Bearer {$workerToken}"])
        ->getJson('/api/dashboard')
        ->assertStatus(403);
});

test('user token cannot access worker portal api endpoints', function () {
    $userToken = $this->user->createToken('test-user', ['panel'])->plainTextToken;

    // Accessing /api/worker/portal/today with user token should be rejected (403)
    $this->withHeaders(['Authorization' => "Bearer {$userToken}"])
        ->getJson('/api/worker/portal/today')
        ->assertStatus(403);
});

test('worker can update their profile without requiring email', function () {
    $workerToken = $this->worker->createToken('test-worker', ['worker'])->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$workerToken}"])
        ->putJson('/api/auth/profile', [
            'name' => 'Updated Worker Name',
            'phone' => '998909998877',
        ]);

    $response->assertStatus(200);

    $this->worker->refresh();
    expect($this->worker->name)->toBe('Updated Worker Name')
        ->and($this->worker->phone)->toBe('998909998877');
});
