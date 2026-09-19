<?php

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
