<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-webhook {--url= : Webhook URL override}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set Telegram bot webhook with secret token';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $botToken = config('services.telegram.bot_token') ?: env('TELEGRAM_BOT_TOKEN');
        if (empty($botToken)) {
            $this->error('TELEGRAM_BOT_TOKEN is not configured.');
            return 1;
        }

        $appUrl = rtrim(config('app.url'), '/');
        $webhookUrl = $this->option('url') ?: "{$appUrl}/telegram/handle";
        $secretToken = config('services.telegram.webhook_secret') ?: env('TELEGRAM_WEBHOOK_SECRET');

        $this->info("Setting Telegram webhook to: {$webhookUrl}");

        $payload = [
            'url' => $webhookUrl,
        ];

        if (!empty($secretToken)) {
            $payload['secret_token'] = $secretToken;
            $this->info('Including secret_token.');
        } else {
            $this->warn('No TELEGRAM_WEBHOOK_SECRET configured; registering without secret_token.');
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/setWebhook", $payload);

            if ($response->successful()) {
                $this->info('Webhook set successfully: ' . $response->body());
                return 0;
            }

            $this->error('Failed to set webhook: ' . $response->body());
            return 1;
        } catch (\Exception $e) {
            $this->error('Exception setting webhook: ' . $e->getMessage());
            return 1;
        }
    }
}
