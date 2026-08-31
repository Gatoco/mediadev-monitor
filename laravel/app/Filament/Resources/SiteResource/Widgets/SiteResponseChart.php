<?php

namespace App\Filament\Resources\SiteResource\Widgets;

use App\Models\Site;
use App\Support\Uptime\UptimeHistory;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Chart de respuesta (ms) del sitio individual — 24h por hora.
 */
class SiteResponseChart extends ChartWidget
{
    public ?Site $record = null;

    protected ?string $heading = 'Tiempo de respuesta (24h)';

    protected ?string $color = 'warning';

    protected ?array $options = [
        'plugins' => ['legend' => ['display' => false]],
        'scales' => [
            'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'ms']],
        ],
    ];

    protected function getData(): array
    {
        $now = CarbonImmutable::now();

        $checks = $this->record->uptimeChecks()
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
            'labels' => array_map(fn (array $b) => $b['hour']->format('H:00'), $buckets),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}