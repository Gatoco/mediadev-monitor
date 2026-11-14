<?php

namespace App\Services\Chaos;

use App\Models\ChaosExperiment;
use App\Models\NotificationLog;
use App\Models\Site;
use App\Models\UptimeCheck;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Orquesta el Chaos Lab: inyecta fallas (contenedores o modos del emulador),
 * registra cada una como experimento y mide lo que hizo el monitor:
 * falla provocada → chequeos fallidos → Caído detectado → alerta → recuperado.
 */
class ChaosLab
{
    /** clave => [título, descripción, tipo de objetivo, falla] */
    public const SCENARIOS = [
        'caida_total' => ['Caída total', 'Debería verse Caído en 15 min o menos', 'container', 'stop'],
        'error500' => ['Error 500 sostenido', 'Debería verse Caído en 15 min o menos', 'site', 'error500'],
        'lento' => ['Sitio lento', 'Respuestas de más de 10 s', 'site', 'lento'],
        'degradado' => ['WP degradado', 'Debería verse Degradado al forzar análisis', 'site', 'degradado'],
        'intermitente' => ['Falla intermitente', 'No debería generar alerta', 'site', 'intermitente'],
        'masivo' => ['Incidente masivo', '5 sitios a la vez · pide confirmación', 'site', 'caido'],
    ];

    /** Falla de sitio → escenario con el que se registra si se inyecta a mano. */
    private const MODE_SCENARIO = [
        'caido' => 'caida_total',
        'error500' => 'error500',
        'lento' => 'lento',
        'degradado' => 'degradado',
        'intermitente' => 'intermitente',
    ];

    public const MASSIVE_COUNT = 5;

    public function __construct(
        private ContainerAgent $agent,
        private FaultModes $modes,
    ) {
    }

    // ------------------------------------------------------------ inyección

    public function stopContainer(string $service): ChaosExperiment
    {
        $this->agent->act($this->containerName($service), 'stop');

        return $this->running('container', $service)
            ?? $this->begin('caida_total', 'container', [$service], 'stop', 'down');
    }

    public function startContainer(string $service): void
    {
        $this->agent->act($this->containerName($service), 'start');
        $this->markRestored('container', [$service]);
    }

    public function restartContainer(string $service): void
    {
        $this->agent->act($this->containerName($service), 'restart');
    }

    public function setMode(string $host, string $mode): ?ChaosExperiment
    {
        $this->assertEmulated($host);
        if (!array_key_exists($mode, FaultModes::MODES)) {
            throw new InvalidArgumentException("Modo desconocido: {$mode}");
        }

        // Cambiar de modo cierra la falla anterior de ese sitio.
        $this->markRestored('site', [$host]);
        $this->modes->set($host, $mode);

        if ($mode === 'normal') {
            return null;
        }

        return $this->begin(self::MODE_SCENARIO[$mode], 'site', [$host], $mode, FaultModes::expected($mode));
    }

    /** Lanza un escenario guiado. $target: servicio (container) o host (site). */
    public function runScenario(string $key, ?string $target): ChaosExperiment
    {
        [, , $type, $fault] = self::SCENARIOS[$key] ?? throw new InvalidArgumentException("Escenario desconocido: {$key}");

        if ($type === 'container') {
            $service = $target ?? 'wp-full';
            $this->agent->act($this->containerName($service), 'stop');
            $this->markRestored('container', [$service]);

            return $this->begin($key, 'container', [$service], 'stop', 'down');
        }

        $hosts = $key === 'masivo' ? $this->pickHealthyHosts(self::MASSIVE_COUNT) : [$target ?? $this->pickHealthyHosts(1)[0]];
        foreach ($hosts as $host) {
            $this->assertEmulated($host);
        }

        $this->markRestored('site', $hosts);
        foreach ($hosts as $host) {
            $this->modes->set($host, $fault);
        }

        return $this->begin($key, 'site', $hosts, $fault, FaultModes::expected($fault));
    }

    /** "Subir y terminar": revierte la falla del experimento. */
    public function finish(ChaosExperiment $experiment): ChaosExperiment
    {
        if ($experiment->restored_at === null) {
            foreach ($experiment->targets as $target) {
                $experiment->target_type === 'container'
                    ? $this->agent->act($this->containerName($target), 'start')
                    : $this->modes->set($target, 'normal');
            }
            $experiment->restored_at = now();
            $experiment->save();
        }

        return $this->refresh($experiment);
    }

    /** Botón de pánico: todo arriba, todo Normal, experimentos cerrados. */
    public function restoreAll(): void
    {
        $this->modes->clear();

        $states = $this->agent->states();
        foreach (config('chaos.containers') as $def) {
            if (($states[$def['container']] ?? 'running') !== 'running') {
                $this->agent->act($def['container'], 'start');
            }
        }

        ChaosExperiment::query()->whereNull('restored_at')->update(['restored_at' => now()]);
    }

    // ------------------------------------------------------------ lectura

    /** Recalcula detección/alerta/recuperación y cierra el experimento si corresponde. */
    public function refresh(ChaosExperiment $experiment): ChaosExperiment
    {
        if (!$experiment->isRunning()) {
            return $experiment;
        }

        $sites = $this->sitesFor($experiment);
        $since = CarbonImmutable::instance($experiment->injected_at);

        if ($experiment->detected_at === null) {
            $experiment->detected_at = $this->detectedAt($experiment, $sites, $since);
        }

        if ($experiment->alerted_at === null && $experiment->expected === 'down') {
            $experiment->alerted_at = $this->allLogged($sites, 'down', $since);
        }

        if ($experiment->restored_at !== null) {
            $restored = CarbonImmutable::instance($experiment->restored_at);

            if ($experiment->expected === 'down' && $experiment->detected_at !== null) {
                $experiment->recovered_at ??= $this->recoveredAt($sites, $restored);
                $done = $experiment->recovered_at !== null;
            } else {
                $done = true;
            }

            if ($done) {
                $experiment->status = 'finished';
                $experiment->result = $this->verdict($experiment, $sites) ? 'pass' : 'fail';
            }
        }

        $experiment->save();

        return $experiment;
    }

    public function active(): ?ChaosExperiment
    {
        $experiment = ChaosExperiment::query()->where('status', 'running')->latest('injected_at')->first();

        return $experiment ? $this->refresh($experiment) : null;
    }

    /** Payload completo de un experimento para la UI. */
    public function describe(ChaosExperiment $experiment): array
    {
        $sites = $this->sitesFor($experiment);
        $since = CarbonImmutable::instance($experiment->injected_at);
        $primary = $sites->first();

        $checks = $primary
            ? UptimeCheck::query()->where('site_id', $primary->id)->where('ts', '>=', $since)->orderBy('ts')->limit(6)->get()
            : collect();

        $alerts = NotificationLog::query()
            ->whereIn('site_id', $sites->pluck('id'))
            ->where('sent_at', '>=', $since)
            ->orderBy('sent_at')
            ->get();

        $downAlert = $alerts->firstWhere('kind', 'down');

        return [
            'id' => $experiment->id,
            'scenario' => $experiment->scenario,
            'scenario_label' => self::SCENARIOS[$experiment->scenario][0] ?? $experiment->scenario,
            'target_type' => $experiment->target_type,
            'targets' => $experiment->targets,
            'target_label' => $this->targetLabel($experiment),
            'fault' => $experiment->fault,
            'fault_label' => $experiment->fault === 'stop' ? 'bajado' : FaultModes::label($experiment->fault),
            'expected' => $experiment->expected,
            'status' => $experiment->status,
            'result' => $experiment->result,
            'result_label' => $this->resultLabel($experiment),
            'injected_at' => $experiment->injected_at?->toIso8601String(),
            'detected_at' => $experiment->detected_at?->toIso8601String(),
            'alerted_at' => $experiment->alerted_at?->toIso8601String(),
            'restored_at' => $experiment->restored_at?->toIso8601String(),
            'recovered_at' => $experiment->recovered_at?->toIso8601String(),
            'detection_minutes' => $experiment->detected_at
                ? (int) round($experiment->injected_at->diffInSeconds($experiment->detected_at) / 60)
                : null,
            'alert_channels' => $downAlert?->channels ?? null,
            'alert_delivered' => $downAlert?->delivered ?? null,
            'checks' => $checks->map(fn (UptimeCheck $c) => [
                'ts' => $c->ts?->toIso8601String(),
                'ok' => self::checkOk($c->status),
                'status' => $c->status,
            ])->values()->all(),
            'checks_needed' => \Domain\Uptime\UptimeChecker::THRESHOLD,
            'next_check_at' => $this->nextScheduledCheck()->toIso8601String(),
            'needs_deep' => $experiment->expected === 'wp-degraded',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function history(int $limit = 50): array
    {
        return ChaosExperiment::query()
            ->latest('injected_at')
            ->limit($limit)
            ->get()
            ->map(fn (ChaosExperiment $e) => $this->describe($e->isRunning() ? $this->refresh($e) : $e))
            ->all();
    }

    /** Próximo chequeo del scheduler (everyFiveMinutes = minutos múltiplos de 5). */
    public function nextScheduledCheck(): CarbonImmutable
    {
        $every = (int) config('chaos.check_every_minutes', 5);
        $now = CarbonImmutable::now();
        $next = $now->startOfHour()->addMinutes((intdiv((int) $now->format('i'), $every) + 1) * $every);

        return $next;
    }

    public static function checkOk(?int $status): bool
    {
        return $status !== null && $status >= 200 && $status < 400;
    }

    /** host del sitio a partir de su URL (http://diariotalca.cl:80 → diariotalca.cl). */
    public static function hostOf(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }

    // ------------------------------------------------------------ internos

    private function begin(string $scenario, string $type, array $targets, string $fault, ?string $expected): ChaosExperiment
    {
        return ChaosExperiment::create([
            'scenario' => $scenario,
            'target_type' => $type,
            'targets' => array_values($targets),
            'fault' => $fault,
            'expected' => $expected ?? 'none',
            'injected_at' => now(),
            'status' => 'running',
        ]);
    }

    private function running(string $type, string $target): ?ChaosExperiment
    {
        return ChaosExperiment::query()
            ->where('status', 'running')
            ->where('target_type', $type)
            ->whereNull('restored_at')
            ->get()
            ->first(fn (ChaosExperiment $e) => in_array($target, $e->targets, true));
    }

    /** Marca como revertidos los experimentos abiertos que tocan esos objetivos. */
    private function markRestored(string $type, array $targets): void
    {
        ChaosExperiment::query()
            ->where('target_type', $type)
            ->whereNull('restored_at')
            ->get()
            ->filter(fn (ChaosExperiment $e) => array_intersect($targets, $e->targets) !== [])
            ->each(function (ChaosExperiment $e): void {
                $e->restored_at = now();
                $e->save();
                $this->refresh($e);
            });
    }

    /** @return Collection<int, Site> */
    private function sitesFor(ChaosExperiment $experiment): Collection
    {
        $hosts = $experiment->target_type === 'container'
            ? collect($experiment->targets)->flatMap(fn (string $s) => config("chaos.containers.{$s}.hosts", []))->all()
            : $experiment->targets;

        return Site::query()->get()
            ->filter(fn (Site $s) => in_array(self::hostOf($s->url), $hosts, true))
            ->values();
    }

    private function detectedAt(ChaosExperiment $experiment, Collection $sites, CarbonImmutable $since): ?CarbonInterface
    {
        if ($sites->isEmpty() || $experiment->expected === 'none') {
            return null;
        }

        $times = [];
        foreach ($sites as $site) {
            $at = $experiment->expected === 'wp-degraded'
                ? $this->degradedAt($site, $since)
                : $this->thirdFailureAt($site, $since);

            if ($at === null) {
                return null; // falta al menos un sitio por detectar
            }
            $times[] = $at;
        }

        return collect($times)->max();
    }

    /** Hora del chequeo que completó el umbral de fallos consecutivos. */
    private function thirdFailureAt(Site $site, CarbonImmutable $since): ?CarbonInterface
    {
        $streak = 0;
        $checks = UptimeCheck::query()->where('site_id', $site->id)->where('ts', '>=', $since)->orderBy('ts')->get();

        foreach ($checks as $check) {
            $streak = self::checkOk($check->status) ? 0 : $streak + 1;
            if ($streak >= \Domain\Uptime\UptimeChecker::THRESHOLD) {
                return $check->ts;
            }
        }

        // También vale si el deep lo clasificó como caído.
        $site->refresh();
        $state = $site->current_state instanceof \Domain\SiteRegistry\SiteState ? $site->current_state->value : (string) $site->current_state;

        return $state === 'down' && $site->updated_at?->greaterThanOrEqualTo($since) ? $site->updated_at : null;
    }

    private function degradedAt(Site $site, CarbonImmutable $since): ?CarbonInterface
    {
        $site->refresh();
        $state = $site->current_state instanceof \Domain\SiteRegistry\SiteState ? $site->current_state->value : (string) $site->current_state;

        return $state === 'wp-degraded' && $site->updated_at?->greaterThanOrEqualTo($since) ? $site->updated_at : null;
    }

    /** Momento en que TODOS los sitios tienen una alerta de ese tipo registrada. */
    private function allLogged(Collection $sites, string $kind, CarbonImmutable $since): ?CarbonInterface
    {
        if ($sites->isEmpty()) {
            return null;
        }

        $first = NotificationLog::query()
            ->whereIn('site_id', $sites->pluck('id'))
            ->where('kind', $kind)
            ->where('sent_at', '>=', $since)
            ->get()
            ->groupBy('site_id')
            ->map(fn (Collection $rows) => $rows->min('sent_at'));

        return $first->count() === $sites->count() ? $first->max() : null;
    }

    private function recoveredAt(Collection $sites, CarbonImmutable $restored): ?CarbonInterface
    {
        $times = [];
        foreach ($sites as $site) {
            $ok = UptimeCheck::query()
                ->where('site_id', $site->id)
                ->where('ts', '>=', $restored)
                ->orderBy('ts')
                ->get()
                ->first(fn (UptimeCheck $c) => self::checkOk($c->status));

            if ($ok === null) {
                return null;
            }
            $times[] = $ok->ts;
        }

        return collect($times)->max();
    }

    private function verdict(ChaosExperiment $experiment, Collection $sites): bool
    {
        return match ($experiment->expected) {
            'down' => $experiment->detected_at !== null && $experiment->alerted_at !== null,
            'wp-degraded' => $experiment->detected_at !== null,
            // Intermitente: aprobado si el monitor NO alertó caída durante la falla.
            default => NotificationLog::query()
                ->whereIn('site_id', $sites->pluck('id'))
                ->where('kind', 'down')
                ->whereBetween('sent_at', [$experiment->injected_at, $experiment->restored_at ?? now()])
                ->doesntExist(),
        };
    }

    private function resultLabel(ChaosExperiment $experiment): string
    {
        if ($experiment->isRunning()) {
            return 'En curso';
        }

        if ($experiment->result === 'pass') {
            return match ($experiment->expected) {
                'none' => 'Sin alerta',
                default => max(1, (int) round($experiment->injected_at->diffInSeconds($experiment->detected_at) / 60)) . ' min',
            };
        }

        return $experiment->expected === 'none' ? 'Alertó' : 'No detectado';
    }

    private function targetLabel(ChaosExperiment $experiment): string
    {
        $targets = $experiment->targets;

        return count($targets) > 1 ? count($targets) . ' sitios' : (string) ($targets[0] ?? '');
    }

    /** @return array<int, string> */
    private function pickHealthyHosts(int $count): array
    {
        $injected = array_keys($this->modes->all());
        $free = array_values(array_diff(config('chaos.emulated_hosts'), $injected));
        shuffle($free);

        if (count($free) < $count) {
            throw new InvalidArgumentException('No quedan suficientes sitios en modo Normal.');
        }

        return array_slice($free, 0, $count);
    }

    private function containerName(string $service): string
    {
        $name = config("chaos.containers.{$service}.container");
        if (!is_string($name)) {
            throw new InvalidArgumentException("Contenedor no permitido: {$service}");
        }

        return $name;
    }

    private function assertEmulated(string $host): void
    {
        if (!in_array($host, config('chaos.emulated_hosts'), true)) {
            throw new InvalidArgumentException("Sitio no emulado: {$host}");
        }
    }
}
