<?php

namespace App\Filament\Widgets;

use App\Models\UptimeCheck;
use App\Support\Uptime\UptimeHistory;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Línea de respuesta promedio del fleet por hora (24h, poll 5s).
 */
class FleetResponseChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Respuesta promedio por hora (fleet, 24h)';

    protected int | string | array $columnSpan = 'half';

    protected string $color = 'warning';

    protected ?array $options = [
        'plugins' => ['legend' => ['display' => false]],
        'scales' => [
            'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'ms']],
        ],
    ];

    protected function getData(): array
    {
        $now = CarbonImmutable::now();

        $checks = UptimeCheck::query()
            ->where('ts', '>=', $now->subDay())
            ->whereBetween('status', [200, 399])
            ->get();

        $buckets = UptimeHistory::sparkline($checks, 24, $now);

        return [
            'datasets' => [[
                'label' => 'ms promedio',
                'data' => array_map(fn (array $b) => $b['avg_ms'], $buckets),
                'fill' => false,
            ]],
            'labels' => array_map(
                fn (array $b) => $b['hour']->format('H:00'),
                $buckets,
            ),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}