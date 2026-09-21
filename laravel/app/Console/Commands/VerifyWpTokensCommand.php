<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\Notification\EmailChannel;
use App\Services\Notification\TelegramChannel;
use App\Services\WordPress\WpTokenVerifier;
use Illuminate\Console\Command;

/**
 * Verifica que el Application Password de cada sitio siga funcionando.
 *
 * Corre en el scheduler (diario). Cuando un token pasa de valid/unknown a
 * invalid, dispara UNA notificación (idempotente via notification_states).
 *
 * Uso:
 *   php artisan wp:verify-tokens              verifica todos los sitios con token
 *   php artisan wp:verify-tokens --site=ID    solo uno
 *   php artisan wp:verify-tokens --notify     además de guardar, emite alertas
 */
class VerifyWpTokensCommand extends Command
{
    protected $signature = 'wp:verify-tokens
                            {--site= : id del sitio a verificar (default: todos con token cargado)}
                            {--notify : dispara Telegram/email cuando uno queda inválido}';

    protected $description = 'Prueba los Application Passwords WP contra /wp/v2/users/me';

    public function handle(WpTokenVerifier $verifier): int
    {
        $query = Site::query()
            ->whereNotNull('ap_token')
            ->where('ap_token', '!=', '');

        if ($siteId = $this->option('site')) {
            $query->where('id', (int) $siteId);
        }

        $sites = $query->get();
        if ($sites->isEmpty()) {
            $this->warn('Sin sitios con ap_token cargado.');
            return 0;
        }

        $exit = 0;

        /** @var Site $site */
        foreach ($sites as $site) {
            $prev = $site->wp_token_status ?? WpTokenVerifier::UNKNOWN;
            $result = $verifier->verify($site);
            $new = $result['status'];

            $icon = match ($new) {
                WpTokenVerifier::VALID => '✓',
                WpTokenVerifier::INVALID => '✗',
                WpTokenVerifier::UNREACHABLE => '?',
                default => '·',
            };

            $this->line(sprintf(
                '%s [%-10s] %-30s %s',
                $icon,
                $new,
                $site->name,
                $result['message'],
            ));

            // Transición a inválido: notificar SOLO la primera vez.
            if ($new === WpTokenVerifier::INVALID && $prev !== WpTokenVerifier::INVALID) {
                $this->notifyInvalidToken($site, $result['message']);
                $exit = 1;
            }
        }

        return $exit;
    }

    private function notifyInvalidToken(Site $site, string $message): void
    {
        if (!$this->option('notify')) {
            return;
        }

        $text = implode("\n", [
            "🟡 AP WP INVÁLIDO: {$site->name}",
            "URL: {$site->url}",
            "Detalle: {$message}",
            'Revisa el Application Password en /admin (Sites → editar → Credenciales WP).',
        ]);

        $telegram = new TelegramChannel(
            token: config('notifications.telegram.token'),
            chatId: config('notifications.telegram.chat_id'),
            apiBase: config('notifications.telegram.api_base', 'https://api.telegram.org'),
        );
        if ($telegram->enabled()) {
            $telegram->send($text);
        }

        $email = new EmailChannel(recipients: config('notifications.emails'));
        if ($email->enabled()) {
            $email->send('[mediadev] AP WP inválido: ' . $site->name, $text);
        }
    }
}
