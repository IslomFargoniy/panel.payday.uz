<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function handle(Request $request)
    {
        $secret = config('services.telegram.webhook_secret') ?: env('TELEGRAM_WEBHOOK_SECRET');
        if (!empty($secret)) {
            $headerSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if (!$headerSecret || !hash_equals($secret, $headerSecret)) {
                Log::warning('Telegram webhook rejected: Invalid secret token', [
                    'ip' => $request->ip(),
                ]);
                return response('Unauthorized', 401);
            }
        }

        $update = $request->all();

        $this->telegramService->handleUpdate($update);

        return response('OK', 200);
    }
}
