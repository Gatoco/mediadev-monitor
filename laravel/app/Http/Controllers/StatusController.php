<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Support\Uptime\UptimeHistory;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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

        // La tabla crece sin tope (cron cada minuto): nunca hidratar 7 días de filas.
        // 24 h en crudo (sparkline + uptime 24 h) y 7 d como agregado SQL por sitio.
        $last24BySite = DB::table('uptime_checks')
            ->select(['site_id', 'ts', 'status', 'response_ms'])
            ->where('ts', '>=', $now->subDay())
            ->orderBy('ts')
            ->get()
            ->groupBy('site_id');

        $week = DB::table('uptime_checks')
            ->selectRaw('site_id, COUNT(*) AS total, SUM(CASE WHEN status >= 200 AND status < 400 THEN 1 ELSE 0 END) AS up')
            ->where('ts', '>=', $now->subDays(7))
            ->groupBy('site_id')
            ->get()
            ->keyBy('site_id');

        return $sites->map(function (Site $site) use ($last24BySite, $week, $now) {
            $last24 = $last24BySite->get($site->id, collect());
            $spark = UptimeHistory::sparkline($last24, 24, $now);
            $w = $week->get($site->id);

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
                'uptime_7d' => $w && $w->total > 0 ? round($w->up / $w->total * 100, 1) : null,
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