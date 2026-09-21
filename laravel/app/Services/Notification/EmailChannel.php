<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Canal email: usa el mailer configurado de Laravel (config/mail.php).
 *
 * Con MAIL_MAILER=log los mensajes van al log (staging/producción sin SMTP).
 * Con MAIL_MAILER=smtp salen por el SMTP configurado en el .env.
 * Sin destinatarios (NOTIFY_EMAIL_TO vacío) el canal está deshabilitado.
 */
class EmailChannel
{
    /** @param string[] $recipients */
    public function __construct(private array $recipients)
    {
    }

    /** @return string[] */
    public function recipients(): array
    {
        return $this->recipients;
    }

    public function enabled(): bool
    {
        return $this->recipients !== [];
    }

    public function send(string $subject, string $body): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        try {
            Mail::raw($body, function ($message) use ($subject): void {
                $message->to($this->recipients)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('email.send.failed', ['error' => $e->getMessage()]);
            return false;
        }

        return true;
    }
}
