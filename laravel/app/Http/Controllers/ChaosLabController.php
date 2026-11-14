<?php

namespace App\Http\Controllers;

use App\Console\Commands\ChaosCheckCommand;
use App\Models\ChaosExperiment;
use App\Models\NotificationLog;
use App\Models\Site;
use App\Services\Chaos\ChaosLab;
use App\Services\Chaos\ContainerAgent;
use App\Services\Chaos\FaultModes;
use Domain\SiteRegistry\SiteState;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SemaOPS Chaos Lab: UI + API JSON para provocar fallas en los sitios
 * clonados y cronometrar la reacción del monitor.
 */
class ChaosLabController
{
    /** Peor estado primero: define la luz de un contenedor con varios sitios. */
    private const SEVERITY = ['down' => 4, 'wp-degraded' => 3, 'unknown' => 2, 'non-wp' => 1, 'wp-full' => 0];

    public function __construct(
        private ChaosLab $lab,
        private ContainerAgent $agent,
        private FaultModes $modes,
    ) {
    }

    public function index(): View
    {
        return view('lab.index', [
            'scenarios' => collect(ChaosLab::SCENARIOS)->map(fn ($s, $key) => [
                'key' => $key, 't' => $s[0], 'd' => $s[1], 'type' => $s[2],
            ])->values(),
            'modes' => collect(FaultModes::MODES)->map(fn ($m, $key) => [
                'key' => $key, 'name' => $m[0], 'desc' => $m[1],
            ])->values(),
            'containers' => array_keys(config('chaos.containers')),
            'emulatedCount' => count(config('chaos.emulated_hosts')),
        ]);
    }

    /** GET /lab/api/state — todo lo que necesita la pantalla Inicio. */
    public function state(): JsonResponse
    {
        $sitesByHost = Site::query()->get()->keyBy(fn (Site $s) => ChaosLab::hostOf($s->url));
        $states = $this->agent->states();
        $modes = $this->modes->all();

        $containers = collect(config('chaos.containers'))->map(function (array $def, string $service) use ($sitesByHost, $states) {
            $sites = collect($def['hosts'])->map(fn ($h) => $sitesByHost->get($h))->filter();
            $worst = $sites->map(fn (Site $s) => $this->stateOf($s))
                ->sortByDesc(fn ($s) => self::SEVERITY[$s] ?? 2)
                ->first();

            return [
                'service' => $service,
                'container_state' => $states[$def['container']] ?? 'unknown',
                'monitor_state' => $worst ?? 'unmonitored',
                'sites' => $sites->count(),
            ];
        })->values();

        $sites = collect(config('chaos.emulated_hosts'))->map(fn (string $host) => [
            'host' => $host,
            'monitor_state' => ($site = $sitesByHost->get($host)) ? $this->stateOf($site) : 'unmonitored',
            'failures' => $site?->consecutive_failures ?? 0,
            'mode' => $modes[$host] ?? 'normal',
        ])->values();

        $active = $this->lab->active();

        return response()->json([
            'now' => now()->toIso8601String(),
            'agent_ok' => $states !== [],
            'checking' => Cache::has(ChaosCheckCommand::FLAG),
            'containers' => $containers,
            'sites' => $sites,
            'experiment' => $active ? $this->lab->describe($active) : null,
        ]);
    }

    public function container(string $service, string $action): JsonResponse
    {
        return $this->attempt(function () use ($service, $action) {
            return match ($action) {
                'stop' => ['experiment' => $this->lab->describe($this->lab->stopContainer($service))],
                'start' => tap(['ok' => true], fn () => $this->lab->startContainer($service)),
                'restart' => tap(['ok' => true], fn () => $this->lab->restartContainer($service)),
                default => throw new InvalidArgumentException("Acción desconocida: {$action}"),
            };
        });
    }

    public function mode(Request $request, string $host): JsonResponse
    {
        $mode = (string) $request->input('mode', 'normal');

        return $this->attempt(function () use ($host, $mode) {
            $experiment = $this->lab->setMode($host, $mode);

            return ['experiment' => $experiment ? $this->lab->describe($experiment) : null];
        });
    }

    public function scenario(Request $request, string $key): JsonResponse
    {
        $target = $request->input('target');

        return $this->attempt(fn () => [
            'experiment' => $this->lab->describe($this->lab->runScenario($key, is_string($target) ? $target : null)),
        ]);
    }

    public function experiment(ChaosExperiment $experiment): JsonResponse
    {
        return response()->json($this->lab->describe($this->lab->refresh($experiment)));
    }

    public function finish(ChaosExperiment $experiment): JsonResponse
    {
        return $this->attempt(fn () => $this->lab->describe($this->lab->finish($experiment)));
    }

    public function restore(): JsonResponse
    {
        return $this->attempt(function () {
            $this->lab->restoreAll();

            return ['ok' => true];
        });
    }

    /** POST /lab/api/check — lanza chaos:check en segundo plano. */
    public function check(Request $request): JsonResponse
    {
        $deep = $request->boolean('deep');

        Cache::put(ChaosCheckCommand::FLAG, true, now()->addSeconds($deep ? 300 : 120));

        $command = sprintf(
            'nohup %s %s chaos:check %s > /dev/null 2>&1 &',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('artisan')),
            $deep ? '--deep' : '',
        );
        exec($command);

        return response()->json(['ok' => true, 'deep' => $deep]);
    }

    public function alerts(): JsonResponse
    {
        $rows = NotificationLog::query()
            ->whereIn('kind', ['down', 'recovered'])
            ->latest('sent_at')
            ->limit(50)
            ->get()
            ->map(fn (NotificationLog $n) => [
                'kind' => $n->kind,
                'site' => $n->site_name,
                'text' => $n->text,
                'channels' => $n->channels,
                'delivered' => $n->delivered,
                'sent_at' => $n->sent_at?->toIso8601String(),
            ]);

        return response()->json(['alerts' => $rows]);
    }

    public function history(): JsonResponse
    {
        return response()->json(['experiments' => $this->lab->history()]);
    }

    public function export(): StreamedResponse
    {
        $rows = $this->lab->history(500);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'escenario', 'objetivo', 'falla', 'provocada', 'detectada', 'alerta', 'restaurada', 'recuperada', 'minutos_deteccion', 'canales', 'resultado']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['id'], $r['scenario_label'], implode(' ', $r['targets']), $r['fault_label'],
                    $r['injected_at'], $r['detected_at'], $r['alerted_at'], $r['restored_at'], $r['recovered_at'],
                    $r['detection_minutes'], $r['alert_channels'], $r['result_label'],
                ]);
            }
            fclose($out);
        }, 'chaos-lab-historial.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    private function stateOf(Site $site): string
    {
        return $site->current_state instanceof SiteState ? $site->current_state->value : (string) $site->current_state;
    }

    /** Ejecuta una acción y traduce errores esperables a JSON legible. */
    private function attempt(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (RuntimeException | \Illuminate\Http\Client\ConnectionException $e) {
            return response()->json(['error' => 'No se pudo contactar al chaos-agent: ' . $e->getMessage()], 502);
        }
    }
}
