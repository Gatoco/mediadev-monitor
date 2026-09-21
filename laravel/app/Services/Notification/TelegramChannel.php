<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Log;

/**
 * Canal Telegram vía HTTP API del Bot (curl nativo, cero dependencias).
 *
 * Si falta token o chat_id el canal se considera deshabilitado y send()
 * devuelve false sin lanzar — el agente sigue con los otros canales.
 */
class TelegramChannel
{
    public function __construct(
        private ?string $token,
        private ?string $chatId,
        private string $apiBase = 'https://api.telegram.org',
    ) {
    }

    public function enabled(): bool
    {
        return $this->token !== null && $this->token !== ''
            && $this->chatId !== null && $this->chatId !== '';
    }

    public function send(string $text): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $url = rtrim($this->apiBase, '/') . "/bot{$this->token}/sendMessage";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 8000,
            CURLOPT_CONNECTTIMEOUT_MS => 4000,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'chat_id' => $this->chatId,
                'text' => $text,
                'disable_web_page_preview' => true,
            ]),
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = $raw === false ? curl_error($ch) : null;
        curl_close($ch);

        if ($raw === false || $status !== 200) {
            Log::warning('telegram.send.failed', ['status' => $status, 'error' => $error]);
            return false;
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
            Log::warning('telegram.send.api_error', ['body' => $raw]);
            return false;
        }

        return true;
    }
}
