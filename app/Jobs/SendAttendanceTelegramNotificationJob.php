<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;

class SendAttendanceTelegramNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public $backoff = [5, 15, 30];

    public string $photoPath;
    public string $caption;
    public array $chatIds;

    /**
     * Create a new job instance.
     *
     * @param string $photoPath Absolute path to photo
     * @param string $caption Telegram caption text
     * @param array $chatIds List of chat IDs to notify
     */
    public function __construct(string $photoPath, string $caption, array $chatIds)
    {
        $this->photoPath = $photoPath;
        $this->caption = $caption;
        $this->chatIds = array_values(array_unique(array_filter($chatIds)));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->chatIds)) {
            Log::warning("SendAttendanceTelegramNotificationJob: No chatIds provided.");
            return;
        }

        if (!file_exists($this->photoPath)) {
            Log::warning("SendAttendanceTelegramNotificationJob: Photo file not found: {$this->photoPath}");
            return;
        }

        $token = config('services.telegram.bot_token') ?: env('TELEGRAM_BOT_TOKEN');
        if (!$token) {
            Log::warning('SendAttendanceTelegramNotificationJob: TELEGRAM_BOT_TOKEN is not configured.');
            return;
        }

        $telegram = new Api($token);

        foreach ($this->chatIds as $chatId) {
            try {
                $response = $telegram->sendPhoto([
                    'chat_id'    => $chatId,
                    'photo'      => InputFile::create($this->photoPath),
                    'caption'    => $this->caption,
                ]);
                $msgId = is_object($response) && method_exists($response, 'getMessageId') ? $response->getMessageId() : 'ok';
                Log::info("SendAttendanceTelegramNotificationJob: Sent successfully to chat_id {$chatId}, msg_id: {$msgId}");
            } catch (\Exception $e) {
                Log::error("SendAttendanceTelegramNotificationJob failed for chat_id {$chatId}: " . $e->getMessage());
            }
        }
    }
}
