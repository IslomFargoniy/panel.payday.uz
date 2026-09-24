<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Models\Worker\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $login = trim($request->input('email'));
        $password = $request->input('password');
        $deviceName = $request->input('device_name', 'android-device');

        // 1. Try to authenticate as Admin/Client User
        $user = User::where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if ($user && Hash::check($password, $user->password)) {
            $token = $user->createToken($deviceName)->plainTextToken;
            return response()->json([
                'success' => true,
                'message' => 'Muvaffaqiyatli tizimga kirdingiz.',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'roles' => $user->roles->pluck('name'),
                        'role' => $user->roles->pluck('name')->first() ?? 'Admin',
                    ]
                ]
            ]);
        }

        // 2. Try to authenticate as Worker
        $cleanPhone = preg_replace('/[^0-9]/', '', $login);
        $phoneCandidates = array_unique(array_filter([
            $login,
            $cleanPhone,
            strlen($cleanPhone) >= 9 ? substr($cleanPhone, -9) : null,
            strlen($cleanPhone) >= 9 ? ('+998' . substr($cleanPhone, -9)) : null,
            strlen($cleanPhone) >= 9 ? ('998' . substr($cleanPhone, -9)) : null,
        ]));

        $worker = Worker::with('branch')
            ->whereIn('phone', $phoneCandidates)
            ->orWhere('employeeNoString', $login)
            ->first();

        if ($worker) {
            $passwordMatches = false;

            if (!empty($worker->password)) {
                $passwordMatches = Hash::check($password, $worker->password);
            } else {
                // If worker has no password set yet, allow initial login with employeeNoString or last 4/6 digits of phone
                $allowedInitial = array_filter([
                    (string)$worker->employeeNoString,
                    $worker->phone ? substr($worker->phone, -4) : null,
                    $worker->phone ? substr($worker->phone, -6) : null,
                    '123456',
                ]);

                if (in_array((string)$password, $allowedInitial, true)) {
                    $passwordMatches = true;
                    // Auto-hash their password for future security
                    $worker->password = Hash::make($password);
                    $worker->save();
                }
            }

            if ($passwordMatches) {
                $token = $worker->createToken($deviceName)->plainTextToken;
                return response()->json([
                    'success' => true,
                    'message' => 'Xush kelibsiz!',
                    'data' => [
                        'token' => $token,
                        'user' => [
                            'id' => $worker->id,
                            'name' => $worker->name,
                            'email' => ($worker->phone ?? $worker->id) . '@payday.uz',
                            'phone' => $worker->phone,
                            'roles' => ['Worker'],
                            'role' => 'Worker',
                            'worker_id' => $worker->id,
                            'branch_id' => $worker->branch_id,
                            'branch_name' => $worker->branch?->name,
                            'avatar' => $worker->avatar,
                        ]
                    ]
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Email/Telefon raqam yoki parol noto‘g‘ri.',
        ], 401);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof Worker) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => ($user->phone ?? $user->id) . '@payday.uz',
                    'phone' => $user->phone,
                    'roles' => ['Worker'],
                    'role' => 'Worker',
                    'worker_id' => $user->id,
                    'branch_id' => $user->branch_id,
                    'branch_name' => $user->branch?->name,
                    'avatar' => $user->avatar,
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->roles->pluck('name'),
                'role' => $user->roles->pluck('name')->first() ?? 'Admin',
            ]
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tizimdan muvaffaqiyatli chiqildi.',
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20|unique:users,phone,' . $user->id,
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil muvaffaqiyatli yangilandi.',
            'data' => $user,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Joriy parol noto‘g‘ri.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Parol muvaffaqiyatli yangilandi.',
        ]);
    }
}
