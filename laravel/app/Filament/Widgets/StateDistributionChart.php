<?php

namespace App\Filament\Widgets;

use App\Models\Site;
use Domain\SiteRegistry\SiteState;
use Filament\Widgets\ChartWidget;

/**
 * Doughnut con la distribución de estados del fleet (poll 5s nativo).
 */
class StateDistributionChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Distribución de estados';

    protected int | string | array $columnSpan = 'half';

    protected ?array $options = [
        'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
    ];

    protected function getData(): array
    {
        $sites = Site::query()->get();

        $labels = ['OK (wp-full)', 'Degradado', 'No-WP', 'Caído', 'Sin datos'];
        $colors = ['#10b981', '#f59e0b', '#3b82f6', '#ef4444', '#64748b'];

        $byState = $sites->countBy(fn (Site $site) => $site->current_state?->value ?? 'unknown');

        return [
            'datasets' => [[
                'data' => [
                    $byState->get('wp-full', 0),
                    $byState->get('wp-degraded', 0),
                    $byState->get('non-wp', 0),
                    $byState->get('down', 0),
                    $byState->get('unknown', 0),
                ],
                'backgroundColor' => $colors,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}