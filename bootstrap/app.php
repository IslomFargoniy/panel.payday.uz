<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // ✅ Telegram webhook uchun CSRF istisno
        $middleware->validateCsrfTokens(except: [
            'telegram/handle'
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

if (!function_exists('telegramlog')) {
    function telegramlog($text)
    {
        $token = config('services.telegram.bot_token') ?: env('TELEGRAM_BOT_TOKEN');
        $chat_id = config('services.telegram.log_chat_id') ?: env('TELEGRAM_LOG_CHAT_ID', '531110501');

        if (!$token || !$chat_id) {
            return null;
        }

        try {
            $telegram = new \Telegram\Bot\Api($token);

            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => is_string($text) ? $text : json_encode($text, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'parse_mode' => 'html',
            ]);

            return 1;
        } catch (\Exception $exception) {
            \Illuminate\Support\Facades\Log::error('Telegram API Error: ' . $exception->getMessage());
            return $exception->getMessage();
        }
    }
}
