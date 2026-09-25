<?php

namespace App\Services\Gateway;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppClientService
{
    protected string $token;

    protected string $phoneNumberId;

    public function __construct()
    {
        $this->token = (string) config('gateway.hub.whatsapp.token');
        $this->phoneNumberId = (string) config('gateway.hub.whatsapp.phone_number_id');
    }

    /**
     * Send a plain text message via Meta WhatsApp Business Cloud API.
     */
    public function sendMessage(string $recipientPhone, string $message): bool
    {
        if (empty($this->token) || empty($this->phoneNumberId)) {
            Log::warning('WhatsAppClientService: Missing WhatsApp API credentials in configuration.');

            return false;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $recipientPhone);

        $url = sprintf('https://graph.facebook.com/v18.0/%s/messages', $this->phoneNumberId);

        $response = Http::withToken($this->token)->post($url, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $cleanPhone,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ]);

        if (! $response->successful()) {
            Log::error('WhatsApp API sending failed', [
                'to' => $cleanPhone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }
}
