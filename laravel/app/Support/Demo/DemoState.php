<?php

namespace App\Support\Demo;

use Illuminate\Support\Facades\Cache;

/**
 * Overlay de demo controlado desde el celular. Solo vive en caché: no toca
 * sites ni uptime_checks, así que la telemetría real y las alertas siguen intactas.
 */
final class DemoState
{
    public const STATES = ['down', 'wp-degraded'];

    private const KEY = 'demo.state';

    /** @return array{seq: int, sites: array<int, string>} */
    public static function get(): array
    {
        return Cache::get(self::KEY, ['seq' => 0, 'sites' => []]);
    }

    /**
     * @param  array<int, int>  $ids
     * @param  string|null  $state  down | wp-degraded | null (vuelve a la telemetría real)
     * @return array{seq: int, sites: array<int, string>}
     */
    public static function apply(array $ids, ?string $state): array
    {
        $current = self::get();
        foreach ($ids as $id) {
            if ($state === null) {
                unset($current['sites'][$id]);
            } else {
                $current['sites'][$id] = $state;
            }
        }
        $current['seq']++;
        Cache::forever(self::KEY, $current);

        return $current;
    }

    /** @return array{seq: int, sites: array<int, string>} */
    public static function reset(): array
    {
        $next = ['seq' => self::get()['seq'] + 1, 'sites' => []];
        Cache::forever(self::KEY, $next);

        return $next;
    }
}
