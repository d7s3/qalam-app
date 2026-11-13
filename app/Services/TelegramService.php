<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * The few Telegram Bot API calls the complaints bot makes.
 */
class TelegramService
{
    /** Bots may only download files up to this size. */
    public const LARGEST_DOWNLOAD = 20 * 1024 * 1024;

    public function configured(): bool
    {
        return filled(config('services.telegram.bot_token'));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function call(string $method, array $params = []): Response
    {
        return Http::timeout(30)->asJson()
            ->post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/'.$method, $params);
    }

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     */
    public function send(string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $this->call('sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $replyMarkup,
        ]));
    }

    /** Pass a sender's photo or clip on to someone else, as it was sent. */
    public function copy(string $toChatId, string $fromChatId, int $messageId): void
    {
        $this->call('copyMessage', ['chat_id' => $toChatId, 'from_chat_id' => $fromChatId, 'message_id' => $messageId]);
    }

    /**
     * Keep a sent file on the local disk; the path it was kept at, or null
     * when Telegram would not hand it over.
     */
    public function download(string $fileId, string $pathWithoutExtension): ?string
    {
        $file = $this->call('getFile', ['file_id' => $fileId])->json('result');

        if (! is_array($file) || empty($file['file_path']) || ($file['file_size'] ?? 0) > self::LARGEST_DOWNLOAD) {
            return null;
        }

        $body = Http::timeout(120)
            ->get('https://api.telegram.org/file/bot'.config('services.telegram.bot_token').'/'.$file['file_path']);

        if (! $body->successful()) {
            return null;
        }

        $path = $pathWithoutExtension.'.'.(pathinfo($file['file_path'], PATHINFO_EXTENSION) ?: 'bin');
        Storage::disk('local')->put($path, $body->body());

        return $path;
    }
}
