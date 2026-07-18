<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $phoneNumberId;
    protected string $accessToken;

    public function __construct()
    {
        $this->phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        $this->accessToken   = env('WHATSAPP_ACCESS_TOKEN');
    }

    protected function send(array $payload)
    {
        $url = "https://graph.facebook.com/v20.0/{$this->phoneNumberId}/messages";

        Log::info("WhatsApp payload", ['payload' => $payload]);

        $response = Http::withToken($this->accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error("WhatsApp API Error", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        }

        return $response->json();
    }

    public function sendText(string $to, string $message)
    {
        return $this->send([
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $to,
            "type" => "text",
            "text" => ["body" => $message]
        ]);
    }

    public function sendTemplateWithParams(string $to, string $templateName, array $params, string $lang = "fr")
    {
        $components = [[
            "type" => "body",
            "parameters" => array_map(fn($param) => ["type" => "text", "text" => $param], $params)
        ]];

        return $this->send([
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "template",
            "template" => [
                "name" => $templateName,
                "language" => ["code" => $lang],
                "components" => $components
            ]
        ]);
    }

    public function sendInteractiveButtons(string $to, string $text, array $buttons)
    {
        $payload = [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "body" => ["text" => $text],
                "action" => [
                    "buttons" => array_map(fn($btn) => [
                        "type" => "reply",
                        "reply" => ["id" => $btn["id"], "title" => $btn["title"]]
                    ], $buttons)
                ]
            ]
        ];

        return $this->send($payload);
    }
}
