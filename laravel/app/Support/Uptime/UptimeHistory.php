<?php

namespace App\Support\Uptime;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Métricas derivadas de la serie uptime_checks (puro, testeable, sin DB).
 *
 * - percentage(): % de checks exitosos (status 200..399) en una ventana.
 * - sparkline(): compone la serie en buckets horarios con estado agregado.
 */
class UptimeHistory
{
    /** Umbral de "up": cualquier 2xx/3xx (redirects del home cuentan como vivo). */
    public static function isUp(?int $status): bool
    {
        return $status !== null && $status >= 200 && $status < 400;
    }

    /**
     * Uptime % sobre una colección de checks (cada uno con `status`).
     * null cuando la ventana no tiene checks (sitio nuevo — no penalizar).
     *
     * @param  Collection<int, object{status: ?int}>  $checks
     */
    public static function percentage(Collection $checks): ?float
    {
        if ($checks->isEmpty()) {
            return null;
        }

        $up = $checks->filter(fn ($c) => self::isUp($c->status))->count();

        return round($up / $checks->count() * 100, 1);
    }

    /**
     * Comprime checks (orden DESC por ts) en N buckets horarios.
     *
     * Retorna array de buckets ASC cronológicamente; cada bucket:
     *   ['hour' => CarbonImmutable, 'up' => int, 'total' => int, 'avg_ms' => ?int]
     * Buckets sin checks tienen total=0 (la vista los dibuja "vacíos").
     *
     * @param  Collection<int, object{ts: mixed, status: ?int, response_ms: ?int}>  $checks
     * @return array<int, array{hour: CarbonImmutable, up: int, total: int, avg_ms: ?int}>
     */
    public static function sparkline(Collection $checks, int $hours = 24, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $start = $now->subHours($hours - 1)->startOfHour();

        $buckets = [];
        for ($i = 0; $i < $hours; $i++) {
            $hour = $start->addHours($i);
            $buckets[$hour->format('Y-m-d H')] = [
                'hour' => $hour,
                'up' => 0,
                'total' => 0,
                'ms_sum' => 0,
            ];
        }

        foreach ($checks as $check) {
            $ts = $check->ts instanceof \DateTimeInterface
                ? CarbonImmutable::instance($check->ts)
                : CarbonImmutable::parse($check->ts);

            $key = $ts->format('Y-m-d H');
            if (! isset($buckets[$key])) {
                continue; // fuera de la ventana
            }
            $buckets[$key]['total']++;
            if (self::isUp($check->status)) {
                $buckets[$key]['up']++;
            }
            $buckets[$key]['ms_sum'] += (int) ($check->response_ms ?? 0);
        }

        return array_map(
            fn (array $b) => [
                'hour' => $b['hour'],
                'up' => $b['up'],
                'total' => $b['total'],
                'avg_ms' => $b['total'] > 0 ? (int) round($b['ms_sum'] / $b['total']) : null,
            ],
            array_values($buckets),
        );
    }
}
