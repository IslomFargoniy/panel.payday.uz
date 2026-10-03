<?php

use App\Enums\UserStatus;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('existing users in database default to approved and can access dashboard', function () {
    $user = User::factory()->create([
        'status' => 'approved',
    ]);
    $user->assignRole('Client');

    expect($user->status)->toBe('approved')
        ->and($user->isApproved())->toBeTrue();

    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertStatus(200);
});

test('newly registered user has waiting status and is redirected to pending-approval', function () {
    $response = $this->post('/register', [
        'name' => 'Yangi Foydalanuvchi',
        'phone' => '998901234567',
        'email' => 'yangi@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect(route('pending.approval'));

    $user = User::where('email', 'yangi@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->status)->toBe(UserStatus::WAITING->value)
        ->and($user->isWaiting())->toBeTrue()
        ->and($user->isApproved())->toBeFalse();
});

test('user with waiting status cannot access dashboard and is redirected to pending-approval', function () {
    $user = User::factory()->create([
        'status' => UserStatus::WAITING->value,
    ]);
    $user->assignRole('Client');

    expect($user->isWaiting())->toBeTrue();

    // Trying to visit dashboard
    $dashboardResponse = $this->actingAs($user)->get('/dashboard');
    $dashboardResponse->assertRedirect(route('pending.approval'));

    // Trying to visit another protected route
    $workerResponse = $this->actingAs($user)->get('/worker');
    $workerResponse->assertRedirect(route('pending.approval'));

    // Visiting pending-approval itself is allowed
    $pendingResponse = $this->actingAs($user)->get('/pending-approval');
    $pendingResponse->assertStatus(200);
});

test('approved user visiting pending-approval is automatically redirected to dashboard', function () {
    $user = User::factory()->create([
        'status' => UserStatus::APPROVED->value,
    ]);
    $user->assignRole('Client');

    $response = $this->actingAs($user)->get('/pending-approval');
    $response->assertRedirect(route('dashboard'));
});

test('new Google OAuth user is created with waiting status and redirected to pending-approval', function () {
    $googleUser = Mockery::mock(SocialiteUser::class);
    $googleUser->shouldReceive('getId')->andReturn('google-id-999');
    $googleUser->shouldReceive('getEmail')->andReturn('google.new@example.com');
    $googleUser->shouldReceive('getName')->andReturn('Google New User');
    $googleUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');

    $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get('/auth/google/callback');
    $response->assertRedirect(route('pending.approval'));

    $user = User::where('email', 'google.new@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->status)->toBe(UserStatus::WAITING->value)
        ->and($user->google_id)->toBe('google-id-999')
        ->and($user->isWaiting())->toBeTrue();
});

test('existing approved user logging in via Google retains approved status and redirects to dashboard', function () {
    $user = User::factory()->create([
        'email' => 'google.existing@example.com',
        'status' => UserStatus::APPROVED->value,
    ]);
    $user->assignRole('Client');

    $googleUser = Mockery::mock(SocialiteUser::class);
    $googleUser->shouldReceive('getId')->andReturn('google-id-888');
    $googleUser->shouldReceive('getEmail')->andReturn('google.existing@example.com');
    $googleUser->shouldReceive('getName')->andReturn('Google Existing User');
    $googleUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/existing.jpg');

    $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get('/auth/google/callback');
    $response->assertRedirect(url('/dashboard'));

    $user->refresh();
    expect($user->status)->toBe(UserStatus::APPROVED->value)
        ->and($user->google_id)->toBe('google-id-888');
});

test('admin can view /user with tabs and approve waiting user', function () {
    $admin = User::factory()->create([
        'status' => UserStatus::APPROVED->value,
    ]);
    $admin->assignRole('Admin');

    $waitingUser = User::factory()->create([
        'name' => 'Kutayotgan User',
        'status' => UserStatus::WAITING->value,
    ]);
    $waitingUser->assignRole('Client');

    // Admin views waiting tab
    $viewResponse = $this->actingAs($admin)->get('/user?tab=waiting');
    $viewResponse->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('user/index')
            ->where('tab', 'waiting')
            ->has('counts.waiting')
            ->has('counts.approved')
        );

    // Admin approves waiting user
    $approveResponse = $this->actingAs($admin)->post("/user/{$waitingUser->id}/approve");
    $approveResponse->assertSessionHas('success');

    $waitingUser->refresh();
    expect($waitingUser->status)->toBe(UserStatus::APPROVED->value)
        ->and($waitingUser->isApproved())->toBeTrue();

    // Now the approved user can enter dashboard
    $dashboardResponse = $this->actingAs($waitingUser)->get('/dashboard');
    $dashboardResponse->assertStatus(200);
});

test('non-admin user cannot approve waiting users', function () {
    $client = User::factory()->create([
        'status' => UserStatus::APPROVED->value,
    ]);
    $client->assignRole('Client');

    $waitingUser = User::factory()->create([
        'status' => UserStatus::WAITING->value,
    ]);
    $waitingUser->assignRole('Client');

    $response = $this->actingAs($client)->post("/user/{$waitingUser->id}/approve");
    $response->assertStatus(403);

    $waitingUser->refresh();
    expect($waitingUser->status)->toBe(UserStatus::WAITING->value);
});
