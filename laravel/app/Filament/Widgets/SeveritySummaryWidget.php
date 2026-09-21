<?php

namespace App\Filament\Widgets;

use App\Models\Site;
use Filament\Widgets\Widget;

/**
 * Resumen de severidad de versiones WP — tarjetas red/yellow/green
 * con conteo absoluto, prioridad visual sobre rojo.
 */
class SeveritySummaryWidget extends Widget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.severity-summary';

    public function getData(): array
    {
        $sites = Site::query()
            ->with('latestVersion')
            ->get();

        $red = $sites->filter(fn (Site $s) => $s->latestVersion?->severity === 'red')->count();
        $yellow = $sites->filter(fn (Site $s) => $s->latestVersion?->severity === 'yellow')->count();
        $green = $sites->filter(fn (Site $s) => $s->latestVersion?->severity === 'green')->count();

        return [
            'red' => $red,
            'yellow' => $yellow,
            'green' => $green,
        ];
    }
}