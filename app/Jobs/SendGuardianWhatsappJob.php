<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendGuardianWhatsappJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $phone,
        public string $message,
        public string $senderClientId,
    ) {}

    /**
     * Deliver the guardian message through the WhatsApp gateway, reusing the same
     * phone normalization and session-based send as SendWhatsappTasksJob.
     */
    public function handle(): void
    {
        $phone = preg_replace('/[^0-9]/', '', $this->phone);

        if ($phone === '') {
            return;
        }

        if (str_starts_with($phone, '0')) {
            $phone = '966'.substr($phone, 1);
        } elseif (str_starts_with($phone, '5')) {
            $phone = '966'.$phone;
        }

        try {
            $url = config('services.whatsapp.url');
            $response = Http::withHeaders(['X-Api-Key' => config('services.whatsapp.key')])->timeout(10)->post("{$url}/send", [
                'clientId' => $this->senderClientId,
                'phone' => $phone,
                'message' => $this->message,
            ]);

            if (! $response->successful()) {
                Log::error("Failed to send WhatsApp to guardian phone {$phone}: ".$response->body());
            }
        } catch (\Exception $e) {
            Log::error("Exception while sending WhatsApp to guardian phone {$phone}: ".$e->getMessage());
        }
    }
}
