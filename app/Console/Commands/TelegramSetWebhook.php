<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telegram:set-webhook')]
#[Description('Point the complaints bot at this site, so Telegram delivers its messages here')]
class TelegramSetWebhook extends Command
{
    public function handle(TelegramService $telegram): int
    {
        $secret = (string) config('services.telegram.webhook_secret');

        if (! $telegram->configured() || $secret === '') {
            $this->error('Set TELEGRAM_BOT_TOKEN and TELEGRAM_WEBHOOK_SECRET in .env first.');

            return self::FAILURE;
        }

        $response = $telegram->call('setWebhook', [
            'url' => route('telegram.webhook', $secret),
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
            'drop_pending_updates' => true,
        ]);

        if (! $response->json('ok')) {
            $this->error('Telegram refused: '.$response->json('description', $response->body()));

            return self::FAILURE;
        }

        $this->info('The bot now delivers to '.route('telegram.webhook', '…'));

        return self::SUCCESS;
    }
}
