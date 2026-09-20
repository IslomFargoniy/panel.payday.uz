<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;

        // 1. Check custom X-Locale header
        if ($request->hasHeader('X-Locale')) {
            $locale = $request->header('X-Locale');
        }
        // 2. Check cookie
        elseif ($request->hasCookie('locale')) {
            $locale = $request->cookie('locale');
        } elseif ($request->hasCookie('lang')) {
            $locale = $request->cookie('lang');
        }
        // 3. Check session
        elseif (session()->has('locale')) {
            $locale = session('locale');
        }
        // 4. Check query parameter
        elseif ($request->has('lang')) {
            $locale = $request->get('lang');
        }
        // 5. Fallback to Accept-Language header
        elseif ($request->header('Accept-Language')) {
            $headerLang = substr($request->header('Accept-Language'), 0, 2);
            if (in_array($headerLang, ['uz', 'ru', 'en'])) {
                $locale = $headerLang;
            }
        }

        // Validate supported locales
        if (! $locale || ! in_array($locale, ['uz', 'ru', 'en'])) {
            $locale = config('app.locale', 'uz');
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        if ($request->hasSession() && session('locale') !== $locale) {
            session(['locale' => $locale]);
        }

        return $next($request);
    }
}
