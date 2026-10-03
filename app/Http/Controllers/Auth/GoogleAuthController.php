<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google for authentication.
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google callback and authenticate the user.
     */
    public function callback()
    {
        $googleUser = Socialite::driver('google')->user();
        $email = $googleUser->getEmail();

        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'email' => $email,
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'status' => \App\Enums\UserStatus::WAITING->value,
            ]);
            $user->assignRole('Client');
        } else {
            $user->update([
                'name' => $googleUser->getName() ?: $user->name,
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar() ?: $user->avatar,
            ]);

            if (!$user->hasRole('Client') && !$user->hasRole('Admin')) {
                $user->assignRole('Client');
            }
        }

        // Log the user in
        Auth::login($user);

        // Redirect based on approval status
        if ($user->isApproved()) {
            return redirect()->intended('/dashboard');
        }

        return redirect()->route('pending.approval');
    }
}
