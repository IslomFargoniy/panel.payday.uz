<?php

namespace App\Services\Telegram;

use App\Models\User\User;
use App\Models\Worker\Worker;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;

class TelegramService
{
    protected Api $telegram;

    public function __construct()
    {
        $token = config('services.telegram.bot_token') ?: env('TELEGRAM_BOT_TOKEN');
        $this->telegram = new Api($token);
    }

    public function handleUpdate(array $update): void
    {
        // Bot guruh yoki kanalga qo'shilganda (zamonaviy usul)
        $myChatMember = $update['my_chat_member'] ?? null;
        if ($myChatMember) {
            $chatId = $myChatMember['chat']['id'];
            $newStatus = $myChatMember['new_chat_member']['status'] ?? null;
            // Bot a'zo yoki admin sifatida qo'shilsa xabar berish
            if (in_array($newStatus, ['member', 'administrator'])) {
                $this->sendGroupInfo($chatId);
            }
            return;
        }

        $message = $update['message'] ?? $update['channel_post'] ?? null;
        if (!$message) {
            return;
        }

        $chatId = $message['chat']['id'];
        $chatType = $message['chat']['type'] ?? 'private';
        $text = $message['text'] ?? null;
        $contact = $message['contact'] ?? null;

        // Agar guruh yangi yaratilgan bo'lsa yoki kimdir qo'shilgan bo'lsa
        $groupChatCreated = $message['group_chat_created'] ?? null;
        $newChatMembers = $message['new_chat_members'] ?? null;

        if ($groupChatCreated) {
            $this->sendGroupInfo($chatId);
            return;
        }

        if ($newChatMembers) {
            foreach ($newChatMembers as $member) {
                // Agar qo'shilganlardan biri bot bo'lsa (ya'ni bizning botimiz qo'shilgan bo'lishi ehtimoli katta)
                if (!empty($member['is_bot'])) {
                    $this->sendGroupInfo($chatId);
                    return;
                }
            }
            // Oddiy foydalanuvchi qo'shilsa xalaqit bermaymiz
            return;
        }

        // Tizim xabarlarini yoki bo'sh matnlarni e'tiborsiz qoldirish
        if (!$text && !$contact) {
            return;
        }

        // Buyruqlarni tekshirish (masalan: /start, /start@bot_name)
        if (is_string($text) && (str_starts_with(trim($text), '/start') || str_starts_with(trim($text), '/info'))) {
            if ($chatType === 'private') {
                $this->askPhoneNumber($chatId);
            }
            else {
                $this->sendGroupInfo($chatId);
            }
            return;
        }

        // Boshqa holatlar faqat shaxsiy yozishmalarda (private chat) xabar beradi
        if ($chatType === 'private') {
            if ($contact) {
                $this->savePhoneNumber($chatId, $contact);
            }
            else {
                $this->sendUnknownCommand($chatId);
            }
        }
    }

    /**
     * Guruh va kanal uchun ma'lumot jo'natish
     */
    protected function sendGroupInfo(int|string $chatId): void
    {
        // Telegram API bir vaqtning o'zida 'my_chat_member' va 'new_chat_members' xabarlarini 
        // yuborishi mumkin. Ikki marta javob qaytmasligi uchun kesh orqali cheklaymiz.
        $cacheKey = 'telegram_group_info_' . $chatId;
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return; // Agar oxirgi 5 soniyada xabar yuborilgan bo'lsa, chiqib ketadi
        }
        \Illuminate\Support\Facades\Cache::put($cacheKey, true, 5); // 5 soniya davomida bloklash

        $branch = \App\Models\Branch\Branch::where('telegram_group_id', (string)$chatId)->first();

        if ($branch) {
            $text = "✅ Hozirda bu guruh <b>{$branch->name}</b> filiali bilan biriktirilgan.";
        }
        else {
            $text = "⚠️ Bu guruh (yoki kanal) hali biror filialga biriktirilmagan.\n\n";
            $text .= "Quyidagi <b>Chat ID</b> ni nusxalab oling va kerakli filial sozlamalariga kiriting:\n\n";
            $text .= "Chat ID: <code>{$chatId}</code>";
        }

        $this->sendSafeMessage($chatId, $text);
    }

    /**
     * Step 1 — Ask user to share phone number
     */
    protected function askPhoneNumber(int|string $chatId): void
    {
        $keyboard = Keyboard::make([
            'keyboard' => [
                [
                    Keyboard::button([
                        'text' => '📱 Share my phone number',
                        'request_contact' => true,
                    ])
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);

        $this->sendSafeMessage(
            $chatId,
            "👋 Payday Xush kelibsiz. Telefon raqamingizni yuboring",
            $keyboard
        );
    }

    /**
     * Step 2 — Save phone number to DB
     */
    protected function savePhoneNumber(int|string $chatId, array $contact): void
    {
        try {
            // Tozalanib olamiz (faqat raqamlar)
            $cleanPhone = preg_replace('/[^0-9]/', '', $contact['phone_number']);
            // Masalan: "998901234567" bo'lsa, oxirgi 9 taligini ajratib olib izlash 
            // xavfsizroq, bazada format turlicha bo'lishi ehtimoli bor.
            $searchPhone = substr($cleanPhone, -9);

            $user = User::query()
                ->where('phone', 'like', "%{$searchPhone}%")
                ->first();

            $worker = Worker::query()
                ->where('phone', 'like', "%{$searchPhone}%")
                ->first();

            $removeKeyboard = Keyboard::remove(['selective' => false]);

            if ($user || $worker) {

                if ($user) {
                    $user->telegram_id = $chatId;
                    $user->save();

                    $this->sendSafeMessage(
                        $chatId,
                        "👋 Admin Payday tizimiga xush kelibsiz, <b>{$user->name}</b>.",
                        $removeKeyboard
                    );
                }

                if ($worker) {
                    $worker->telegram_id = $chatId;
                    $worker->save();

                    $this->sendSafeMessage(
                        $chatId,
                        "✅ Telefon raqamingiz muvaffaqiyatli tasdiqlandi!\n\n👋 Xush kelibsiz, <b>{$worker->name}</b>!",
                        $removeKeyboard
                    );

                    $webAppKeyboard = Keyboard::make([
                        'inline_keyboard' => [
                            [
                                Keyboard::inlineButton([
                                    'text' => '📱 Davomat (Mini App)',
                                    'web_app' => ['url' => secure_url('/bot/mini-app')]
                                ])
                            ]
                        ]
                    ]);

                    $this->sendSafeMessage(
                        $chatId,
                        "Quyidagi tugma orqali davomat ilovasidan bemalol hisobot topshirishingiz mumkin 👇",
                        $webAppKeyboard
                    );
                }
            }
            else {
                $this->sendSafeMessage(
                    $chatId,
                    "❌ Sizning raqamingiz ({$contact['phone_number']}) bazada topilmadi. Iltimos, admin bilan bog'laning va raqamingizni kiritishini so'rang.",
                    $removeKeyboard
                );
            }
        }
        catch (\Exception $e) {
            Log::error('Error saving Telegram phone: ' . $e->getMessage());
            $this->sendSafeMessage(
                $chatId,
                "❌ Telefon raqamingizni saqlashda xatolik yuz berdi. Iltimos, qayta urinib ko'ring."
            );
        }
    }

    /**
     * Unknown command handler
     */
    protected function sendUnknownCommand(int|string $chatId): void
    {
        try {
            $text = "Noma'lum buyruq.\n";
            $text .= "Agar siz tizimga kirmoqchi bo'lsangiz, iltimos /start buyrug'ini bosing.\n\n";
            $text .= "Agar siz botni guruh yoki kanalga qo'shgan bo'lsangiz, quyidagi Chat ID ni nusxalab oling va tizimga kiriting:\n\n";
            $text .= "Chat ID: " . $chatId;

            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        }
        catch (\Exception $e) {
            Log::error('Telegram sendMessage error: ' . $e->getMessage());
        }
    }

    /**
     * Safe message sender
     */
    protected function sendSafeMessage(int|string $chatId, string $text, Keyboard $keyboard = null): void
    {
        try {
            $params = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ];

            if ($keyboard) {
                $params['reply_markup'] = $keyboard;
            }

            $this->telegram->sendMessage($params);
        }
        catch (\Exception $e) {
            Log::error('Telegram sendMessage error: ' . $e->getMessage());
        }
    }
}