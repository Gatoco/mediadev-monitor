<?php

namespace App\Services\Notification;

use App\Models\NotificationState;
use App\Models\Site;
use App\Models\UptimeCheck;
use App\Models\VersionSnapshot;
use Carbon\CarbonImmutable;
use Domain\SiteRegistry\SiteState;

/**
 * Detector de eventos del monitor.
 *
 * Compara el estado actual de cada sitio contra los snapshots previos
 * (segundo uptime check y segundo version snapshot) y produce eventos:
 *
 *  - down               el sitio acaba de entrar en estado down
 *  - recovered          venía de un down y volvió (flaky si el down duró
 *                       menos de NOTIFY_RECOVERY_WINDOW → falso positivo)
 *  - version_changed    cambió el core de WP entre los últimos 2 snapshots
 *  - info               digest periódico del flujo de páginas (por hora)
 *
 * Misma regla que el uptime collector: éxito = status != 0 y < 400.
 */
class MonitorEventDetector
{
    public function __construct(
        private int $recoveryWindowMinutes,
        private string $cacheFile,
    ) {
    }

    /**
     * @return array{events: array<int, array<string, mixed>>, digests: array<int, array<string, mixed>>}
     */
    public function detect(): array
    {
        $sites = Site::query()
            ->with(['latestUptime', 'latestVersion'])
            ->get();

        $events = [];
        /** @var array<string, string> $currentSnapshots */
        $currentSnapshots = [];

        foreach ($sites as $site) {
            $state = $site->current_state instanceof SiteState
                ? $site->current_state
                : (SiteState::tryFrom((string) $site->current_state) ?? SiteState::UNKNOWN);

            // --- 1. Eventos de estado (down / recovered) ---
            [$lastUptime, $prev] = $this->lastTwoUptimeChecks($site);

            $prevSucceeded = $prev !== null && $prev->status !== null && $prev->status !== 0 && $prev->status < 400;

            if ($state === SiteState::DOWN && $prevSucceeded) {
                $fingerprint = $lastUptime?->id !== null ? 'uc:' . $lastUptime->id : null;
                if ($this->shouldNotify($site, 'down', $fingerprint)) {
                    $events[] = [
                        'kind' => 'down',
                        'site' => $site->name,
                        'url' => $site->url,
                        'failures' => (int) $site->consecutive_failures,
                    ];
                    $this->markNotified($site, 'down', $fingerprint);
                }
            }

            if ($state !== SiteState::DOWN && $prev !== null && !$prevSucceeded) {
                $fingerprint = $lastUptime?->id !== null ? 'uc:' . $lastUptime->id : null;
                if ($this->shouldNotify($site, 'recovered', $fingerprint)) {
                    $flaky = $prev->ts !== null
                        && $prev->ts->greaterThanOrEqualTo(now()->subMinutes($this->recoveryWindowMinutes));

                    $events[] = [
                        'kind' => 'recovered',
                        'site' => $site->name,
                        'url' => $site->url,
                        'flaky' => $flaky,
                        'response_ms' => $site->latestUptime?->response_ms,
                    ];
                    $this->markNotified($site, 'recovered', $fingerprint);
                    // Limpia el down guardado — la próxima caída volverá a notificar.
                    $this->resetNotified($site, 'down');
                }
            }

            // --- 2. Eventos de versión WP (core actualizado) ---
            $version = $this->detectVersionChange($site);
            if ($version !== null) {
                $versionFingerprint = 'vs:' . ($site->latestVersion?->id ?? 'unk');
                if ($this->shouldNotify($site, 'version_changed', $versionFingerprint)) {
                    $events[] = $version + ['site' => $site->name, 'url' => $site->url];
                    $this->markNotified($site, 'version_changed', $versionFingerprint);
                }
            }

            if ($site->latestVersion !== null) {
                $key = $site->latestVersion->core_version
                    ?? $site->latestVersion->ts?->format('c')
                    ?? (string) $site->latestVersion->id;
                $currentSnapshots[$this->cacheKey($site)] = $key;
            }
        }

        $this->flushCache($currentSnapshots);

        // --- 3. Digest por hora (resumen del flujo de páginas) ---
        $hourStart = $this->hourStart();

        $checks = UptimeCheck::query()->where('ts', '>=', $hourStart)->get();
        $failChks = $checks->filter(
            fn (UptimeCheck $chk): bool => $chk->status === null || $chk->status === 0 || $chk->status >= 400
        )->count();

        $byState = [];
        foreach ($sites as $site) {
            $value = $site->current_state instanceof SiteState
                ? $site->current_state->value
                : (string) $site->current_state;
            $byState[$value] = ($byState[$value] ?? 0) + 1;
        }

        $downList = $sites
            ->filter(function (Site $s): bool {
                $value = $s->current_state instanceof SiteState ? $s->current_state->value : (string) $s->current_state;
                return $value === SiteState::DOWN->value;
            })
            ->map(fn (Site $s): array => [
                'name' => $s->name,
                'state' => 'down',
                'failures' => (int) $s->consecutive_failures,
            ])
            ->values()
            ->all();

        $digests = [[
            'kind' => 'info',
            'site' => 'Resumen horario',
            'text' => WatchdogMessages::digest([
                'from' => $hourStart->format('Y-m-d H:i'),
                'to' => now()->format('Y-m-d H:i'),
                'total' => $sites->count(),
                'chks' => $checks->count(),
                'failures' => $failChks,
                'availability' => $checks->count() > 0 ? round(100 * (1 - $failChks / $checks->count()), 2) : null,
                'avg_ms' => $checks->count() > 0 ? (int) round($checks->avg('response_ms') ?? 0) : null,
                'by_state' => $byState,
                'down' => $downList,
                'sev' => [
                    'red' => $sites->filter(fn (Site $s) => $s->latestVersion?->severity === 'red')->count(),
                    'yellow' => $sites->filter(fn (Site $s) => $s->latestVersion?->severity === 'yellow')->count(),
                    'green' => $sites->filter(fn (Site $s) => $s->latestVersion?->severity === 'green')->count(),
                ],
            ]),
        ]];

        return ['events' => $events, 'digests' => $digests];
    }

    /** @return array{kind:string,from:?string,to:?string,severity:string,php:?string}|null */
    private function detectVersionChange(Site $site): ?array
    {
        $latest = VersionSnapshot::query()
            ->where('site_id', $site->id)
            ->orderByDesc('ts')
            ->orderByDesc('id')
            ->first();
        if ($latest === null || $latest->core_version === null || $latest->core_version === '') {
            return null;
        }

        $prev = VersionSnapshot::query()
            ->where('site_id', $site->id)
            ->orderByDesc('ts')
            ->orderByDesc('id')
            ->skip(1)
            ->first();

        $from = $prev?->core_version;
        $to = $latest->core_version;

        if ($from !== null && $from !== $to) {
            return [
                'kind' => 'version_changed',
                'from' => $from,
                'to' => $to,
                'severity' => $latest->severity ?? 'green',
                'php' => null, // PHP vive en site_health (tests_json), no en version_snapshots
            ];
        }

        return null;
    }

    /** Extrae la versión de PHP del site-health tests_json si viene. */
    public function extractPhpVersion(?string $testsJson): ?string
    {
        if ($testsJson === null || $testsJson === '') {
            return null;
        }

        $decoded = json_decode($testsJson, true);
        if (!is_array($decoded)) {
            return null;
        }

        foreach (['direct', 'async'] as $group) {
            foreach ($decoded[$group] ?? [] as $test) {
                $label = strtolower((string) ($test['label'] ?? ''));
                $testName = strtolower((string) ($test['test'] ?? ''));

                if (str_contains($label, 'php') || str_contains($testName, 'php_version')) {
                    if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', (string) ($test['label'] ?? ''), $m)) {
                        return $m[1];
                    }
                    $badge = strtolower((string) ($test['badge']['label'] ?? ''));
                    if (str_contains($badge, 'php') && preg_match('/(\d+\.\d+(?:\.\d+)?)/', $badge, $m)) {
                        return $m[1];
                    }
                }
            }
        }

        return null;
    }

    // --- Cache de snapshots ya notificados (dedupe de versiones) ---

    // --- Utilidades internas ---

    /**
     * Devuelve [último, segundo] uptime check del sitio.
     *
     * @return array{0:?UptimeCheck, 1:?UptimeCheck}
     */
    private function lastTwoUptimeChecks(Site $site): array
    {
        $rows = UptimeCheck::query()
            ->where('site_id', $site->id)
            ->orderByDesc('ts')
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        return [$rows->get(0), $rows->get(1)];
    }

    /**
     * ¿Toca notificar? Solo si nunca se reportó ESTA firma del evento.
     * El fingerprint amarra la alerta al snapshot que la produjo:
     * si el estado sigue igual entre corridas, no se vuelve a enviar.
     */
    private function shouldNotify(Site $site, string $kind, ?string $fingerprint): bool
    {
        if ($fingerprint === null) {
            // Sin firma no hay forma de dedup — preferimos avisar una vez.
            return NotificationState::query()
                ->where('site_id', $site->id)
                ->where('kind', $kind)
                ->whereNull('fingerprint')
                ->doesntExist();
        }

        return NotificationState::query()
            ->where('site_id', $site->id)
            ->where('kind', $kind)
            ->where('fingerprint', $fingerprint)
            ->doesntExist();
    }

    private function markNotified(Site $site, string $kind, ?string $fingerprint): void
    {
        NotificationState::updateOrCreate(
            ['site_id' => $site->id, 'kind' => $kind],
            ['fingerprint' => $fingerprint, 'notified_at' => now()],
        );
    }

    private function resetNotified(Site $site, string $kind): void
    {
        NotificationState::query()
            ->where('site_id', $site->id)
            ->where('kind', $kind)
            ->delete();
    }

    private function cacheKey(Site $site): string
    {
        return 'version:' . $site->url;
    }

    /** @return array<string, string> */
    private function readCache(): array
    {
        if (!is_file($this->cacheFile)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->cacheFile), true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, string> $current */
    private function flushCache(array $current): void
    {
        if ($current === []) {
            return;
        }

        @mkdir(dirname($this->cacheFile), 0755, true);
        file_put_contents($this->cacheFile, json_encode($current, JSON_PRETTY_PRINT) . "\n");
    }

    private function hourStart(): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $hour = (int) $now->format('H');
        $start = $now->startOfHour();

        return $hour === 0 ? $start->subDay()->setTime(23, 0) : $start->subHour();
    }

    /** Formatea el payload de un evento a texto (Telegram/email). */
    public static function render(array $event): string
    {
        return match ($event['kind'] ?? '') {
            'down' => WatchdogMessages::siteDown($event['site'], $event['url'], $event['failures'] ?? 0),
            'recovered' => WatchdogMessages::siteRecovered(
                $event['site'],
                $event['url'],
                $event['response_ms'] ?? null,
                (bool) ($event['flaky'] ?? false),
                0 // el texto de ventana lo ajusta el comando con la config real
            ),
            'version_changed' => WatchdogMessages::versionChanged(
                $event['site'],
                $event['url'],
                $event['from'] ?? null,
                $event['to'] ?? null,
                $event['severity'] ?? 'green',
                $event['php'] ?? null,
            ),
            'info' => $event['text'] ?? 'Resumen mediadev',
            default => 'Evento mediadev: ' . json_encode($event),
        };
    }
}
