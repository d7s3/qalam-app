<?php

namespace App\Http\Controllers;

use App\Services\ComplaintBot;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramWebhookController extends Controller
{
    /**
     * Take an update from Telegram. Always answered 200 once it is genuine,
     * because Telegram resends anything else and the sender would be asked
     * the same question twice.
     */
    public function __invoke(Request $request, string $secret, ComplaintBot $bot): Response
    {
        $expected = (string) config('services.telegram.webhook_secret');

        abort_if(
            $expected === ''
                || ! hash_equals($expected, $secret)
                || ! hash_equals($expected, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')),
            404,
        );

        try {
            $bot->handle($request->all());
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->noContent(200);
    }
}
