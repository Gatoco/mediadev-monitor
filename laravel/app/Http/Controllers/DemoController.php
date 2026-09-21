<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Support\Facades\Cache;

/**
 * Demo pública del panel (read-only, sin auth).
 * Expone solo agregados y nombre/estado — NUNCA tokens, urls internas ni ids.
 * ponytail: sin clase Controller base (no existe en el repo) — extend directo sin padre.
 */
class DemoController
{
    public function page(): \Illuminate\Contracts\View\View
    {
        $sites = Site::query()
            ->select(['id', 'name', 'current_state'])
            ->orderBy('name')
            ->get();

        $lastCheck = \Illuminate\Support\Facades\DB::table('uptime_checks')->max('ts');

        // agrupado por estado, orden narrativo: verde → amarillo → gris → rojo
        $order = ['wp-full', 'wp-degraded', 'non-wp', 'down'];
        $labels = [
            'wp-full'     => 'Operativos',
            'wp-degraded' => 'Requieren atención',
            'non-wp'      => 'Sin WordPress detectado',
            'down'        => 'Caídos',
        ];

        $groups = [];
        foreach ($order as $state) {
            $groups[$state] = [
                'label' => $labels[$state],
                'sites' => $sites->where('current_state->value ?? current_state', $state)
                    ->values(),
            ];
        }
        // cast enum: current_state es SiteState enum — reagrupar por valor
        $groups = [];
        foreach ($order as $state) {
            $in = $sites->filter(fn ($s) => (is_string($s->current_state) ? $s->current_state : $s->current_state?->value) === $state)->values();
            $groups[$state] = ['label' => $labels[$state], 'sites' => $in];
        }

        return view('demo', [
            'groups' => $groups,
            'total' => $sites->count(),
            'lastCheck' => $lastCheck ? $this->relTime($lastCheck) : '—',
        ]);
    }

    public function json(): \Illuminate\Http\JsonResponse
    {
        $payload = Cache::remember('demo-status', 30, function () {
            $sites = Site::query()->select(['id', 'name', 'current_state'])->orderBy('name')->get();
            $lastCheck = \Illuminate\Support\Facades\DB::table('uptime_checks')->max('ts');

            $counts = ['wp_full' => 0, 'wp_degraded' => 0, 'non_wp' => 0, 'down' => 0];
            $list = [];
            foreach ($sites as $s) {
                $state = is_string($s->current_state) ? $s->current_state : ($s->current_state?->value ?? 'unknown');
                $key = str_replace('-', '_', $state);
                if (array_key_exists($key, $counts) === false) {
                    continue;
                }
                $counts[$key]++;
                $last = \Illuminate\Support\Facades\DB::table('uptime_checks')->where('site_id', $s->id)->max('ts');
                $list[] = [
                    'name' => $s->name,
                    'state' => $state,
                    'last_check' => $last,
                ];
            }

            return [
                'generated_at' => now()->toIso8601String(),
                'counts' => $counts,
                'sites' => $list,
            ];
        });

        return response()->json($payload, 200, ['Cache-Control' => 'public, max-age=30']);
    }

    /** "hace 2 min" / "hace 3 h" — null-safe. */
    private function relTime(?string $ts): string
    {
        if ($ts === null || ($t = strtotime($ts)) === false) {
            return 'sin datos';
        }
        $diff = time() - $t;
        if ($diff < 90) {
            return 'hace un momento';
        }
        if ($diff < 3600) {
            return 'hace ' . (int) round($diff / 60) . ' min';
        }
        if ($diff < 86400) {
            return 'hace ' . (int) round($diff / 3600) . ' h';
        }
        // ponytail: demo emulada puede tener checks viejos — fecha directa si supera 1 día
        return 'el ' . date('d-m-Y H:i', $t);
    }
}