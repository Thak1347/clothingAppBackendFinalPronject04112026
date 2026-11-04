<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    public function send(string $message): void
    {
        $token = config('services.telegram.bot_token');
        $chatIds = config('services.telegram.admin_chat_ids');

        if (!$token || !$chatIds) {
            Log::info('[Telegram disabled] ' . $message);
            return;
        }

        foreach (array_filter(array_map('trim', explode(',', $chatIds))) as $chatId) {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
            ]);
        }
    }
}
