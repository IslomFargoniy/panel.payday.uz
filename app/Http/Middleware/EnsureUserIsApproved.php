<?php

namespace App\Http\Middleware;

use App\Models\User\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsApproved
{
    /**
     * Handle an incoming request.
     * Ensure that the authenticated user is approved by superadmin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Mehmonlar yoki User bo'lmagan modellar (masalan Worker tokenlari) bu yerda tekshirilmaydi
        if (!$user || !($user instanceof User)) {
            return $next($request);
        }

        // Admin roli har doim to'siqsiz o'tadi
        if ($user->hasRole('Admin')) {
            return $next($request);
        }

        // Foydalanuvchi tasdiqlangan bo'lsa o'tadi
        if ($user->isApproved()) {
            return $next($request);
        }

        // Ruxsat berilgan maxsus marshrutlar (kutilmoqda sahifasi, logout va til tanlash)
        if ($request->routeIs('pending.approval') || $request->routeIs('logout')) {
            return $next($request);
        }

        // API yoki JSON so'rovlar uchun 403 xatosi
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'error' => 'Hisobingiz superadmin tasdiqlashini kutilmoqda.',
                'status' => $user->status,
            ], 403);
        }

        // Oddiy veb sahifa so'rovlari pending.approval sahifasiga yo'naltiriladi
        return redirect()->route('pending.approval');
    }
}
