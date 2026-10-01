<?php

use App\Models\User\User;

test('payuz routes return 404 not found', function () {
    $response = $this->get('/handle/click');
    $response->assertNotFound();

    $response2 = $this->get('/pay/payme/1/100000');
    $response2->assertNotFound();
});

test('locale route switches language and queues persistent cookie', function () {
    $response = $this->get('/lang/uz');
    $response->assertSessionHas('locale', 'uz');
    $response->assertPlainCookie('locale', 'uz');

    $invalidResponse = $this->get('/lang/invalid_lang');
    $invalidResponse->assertStatus(400);
});

test('root url redirects guest to login and authenticated user to dashboard', function () {
    $guestResponse = $this->get('/');
    $guestResponse->assertRedirect(route('login'));

    $user = User::factory()->create();
    $authResponse = $this->actingAs($user)->get('/');
    $authResponse->assertRedirect(route('dashboard'));
});

test('user registration rejects duplicate phone number', function () {
    User::factory()->create([
        'phone' => '998901234567',
        'email' => 'existing@example.com',
    ]);

    $response = $this->post('/register', [
        'name' => 'New User',
        'phone' => '998901234567',
        'email' => 'new@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertSessionHasErrors(['phone']);
});

test('profile update rejects duplicate phone of another user', function () {
    $user1 = User::factory()->create([
        'phone' => '998901112233',
    ]);
    $user2 = User::factory()->create([
        'phone' => '998909998877',
    ]);

    $response = $this->actingAs($user2)->patch(route('profile.update'), [
        'name' => 'User Two',
        'phone' => '998901112233',
        'email' => $user2->email,
    ]);

    $response->assertSessionHasErrors(['phone']);
});

test('mysql connection strict config respects DB_STRICT env defaulting to false', function () {
    expect(config('database.connections.mysql.strict'))->toBeFalse();
});
