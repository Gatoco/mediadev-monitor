<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\Notification\MonitorEventDetector;
use App\Services\Notification\TelegramChannel;
use App\Services\Notification\EmailChannel;
use Illuminate\Console\Command;

/**
 * Agente de notificaciones de mediadev-monitor.
 *
 * Compara el estado previo/actual y dispara alertas por Telegram + email.
 *
 * Uso: php artisan notify:status
 *      php artisan notify:status --digest  # fuerza el resumen sin eventos
 *
 * Corre cada minuto desde scheduler (eventos) y genera un resumen cada hora.
 */
class NotifyStatusCommand extends Command
{
    protected $signature = 'notify:status {--digest : fuerza el resumen aunque no haya eventos}
                            {--no-channels : no envía, solo imprime (debug)}
                            {--json : imprime la detección como JSON}';

    protected $description = 'Detecta cambios y envía alertas/notificaciones';

    /** @return int 0 = ok, 1 = al menos un canal fallo */
    public function handle(): int
    {
        $detector = new MonitorEventDetector(
            recoveryWindowMinutes: (int) config('notifications.recovery_window_minutes', 15),
            cacheFile: storage_path('app/mediadev/notify-snapshots.json'),
        );

        $result = $detector->detect();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        $sent = 0;
        $failed = 0;

        $sendEvent = function (array $event) use (&$sent, &$failed): void {
            $text = MonitorEventDetector::render($event);
            $ok = $this->dispatchEvent($text);
            $ok ? $sent++ : $failed++;
        };

        // 1. Diff de eventos (down, recupero, update) — siempre.
        foreach ($result['events'] as $event) {
            $sendEvent($event);
        }

        // 2. Resumen periódico (opcional o por hora en punto).
        if ($this->option('digest') || $this->shouldSendDigest($result)) {
            foreach ($result['digests'] as $digest) {
                $sendEvent($digest);
            }
        }

        $this->line(sprintf('Eventos enviados: %d. Fallidos: %d.', $sent, $failed));

        return $failed > 0 ? 1 : 0;
    }

    /** @return bool true si hay que mandar el digest (hora en punto o forzado) */
    private function shouldSendDigest(array $result): bool
    {
        return count($result['events']) === 0 && (int) now()->format('i') < 5;
    }

    /** Despacha un evento a Telegram y email. Retorna true si al menos uno pasó. */
    private function dispatchEvent(string $text): bool
    {
        if ($this->option('no-channels')) {
            $this->info($text);
            return true;
        }

        $ok = false;

        // Telegram — solo canal "obligatorio".
        $telegram = new TelegramChannel(
            token: config('notifications.telegram.token'),
            chatId: config('notifications.telegram.chat_id'),
            apiBase: config('notifications.telegram.api_base', 'https://api.telegram.org'),
        );
        if ($telegram->enabled()) {
            $ok = $telegram->send($text) || $ok;
        }

        // Email — solo si hay destinatarios configurados.
        $email = new EmailChannel(recipients: config('notifications.emails'));
        if ($email->enabled()) {
            $subject = strtok($text, "\n") ?: 'Evento mediadev';
            $ok = $email->send($subject, $text) || $ok;
        }

        return $ok;
    }
}
