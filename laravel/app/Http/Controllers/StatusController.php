<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\UptimeCheck;
use App\Support\Uptime\UptimeHistory;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Página pública de estado en tiempo real.
 *
 * Renderiza la primera vez y sirve JSON para el polling (fetch cada 5s),
 * así el navegador no re-descarga HTML+assets en cada ciclo.
 */
class StatusController
{
    public function index(): View
    {
        return view('status.status', ['sites' => $this->siteData()]);
    }

    /** GET /status.json — payload del polling del grid. */
    public function json(): JsonResponse
    {
        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'sites' => $this->siteData(),
        ]);
    }

    /**
     * Una pasada por sitio: estado, último check, uptime % 24h/7d y sparkline.
     *
     * @return array<int, array{
     *     id: int, name: string, url: string, state: string,
     *     state_label: string, last_ms: ?int, last_status: ?int,
     *     last_check: ?string, failures: int,
     *     uptime_24h: ?float, uptime_7d: ?float, core_version: ?string, sparkline: array<int, array{up: int, total: int, avg_ms: ?int}>,
     * }>
     */
    private function siteData(): array
    {
        $now = CarbonImmutable::now();

        $sites = Site::query()
            ->with(['latestUptime', 'latestVersion'])
            ->orderBy('name')
            ->get();

        // Todos los checks de la última semana en 2 queries (1 por tabla… en
        // realidad una sola: uptime_checks), indexados por site_id.
        $checksBySite = UptimeCheck::query()
            ->where('ts', '>=', $now->subDays(7))
            ->orderBy('ts')
            ->get()
            ->groupBy('site_id');

        return $sites->map(function (Site $site) use ($checksBySite, $now) {
            $checks = $checksBySite->get($site->id, collect());

            $last24 = $checks->filter(fn ($c) => $c->ts >= $now->subDay());
            $spark = UptimeHistory::sparkline($checks, 24, $now);

            $state = $site->current_state instanceof \Domain\SiteRegistry\SiteState
                ? $site->current_state->value
                : (string) $site->current_state;

            return [
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
                'state' => $state,
                'state_label' => self::STATE_LABELS[$state] ?? $state,
                'last_ms' => $site->latestUptime?->response_ms,
                'last_status' => $site->latestUptime?->status,
                'last_check' => $site->latestUptime?->ts?->toIso8601String(),
                'failures' => $site->consecutive_failures,
                'core_version' => $site->latestVersion?->core_version,
                'uptime_24h' => UptimeHistory::percentage($last24),
                'uptime_7d' => UptimeHistory::percentage($checks),
                'sparkline' => array_map(
                    fn (array $b) => ['up' => $b['up'], 'total' => $b['total'], 'avg_ms' => $b['avg_ms']],
                    $spark,
                ),
            ];
        })->values()->all();
    }

    private const STATE_LABELS = [
        'wp-full' => 'OK',
        'wp-degraded' => 'Degradado',
        'non-wp' => 'No-WP',
        'down' => 'Caído',
        'unknown' => 'Sin datos',
    ];
}