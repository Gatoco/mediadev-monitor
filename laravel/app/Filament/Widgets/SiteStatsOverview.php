<?php

namespace App\Filament\Widgets;

use App\Models\Site;
use Domain\SiteRegistry\SiteState;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SiteStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $sites = Site::query()
            ->with(['latestUptime', 'latestVersion'])
            ->get();

        $byState = $sites->countBy(fn (Site $site) => $site->current_state instanceof SiteState
            ? $site->current_state->value
            : (string) $site->current_state);

        $avgResponse = (int) round($sites->avg(fn (Site $site) => $site->latestUptime?->response_ms) ?? 0);

        $redCount = $sites->filter(fn (Site $site) => $site->latestVersion?->severity === 'red')->count();
        $downCount = $byState->get('down', 0);
        $degradedCount = $byState->get('wp-degraded', 0);

        return [
            Stat::make('Total sitios', $sites->count())
                ->description('Cartera monitoreada')
                ->icon(Heroicon::ServerStack)
                ->color('gray'),

            Stat::make('Sitios caídos', $downCount)
                ->description($downCount > 0 ? '⚠ Atención requerida' : 'Todos arriba')
                ->icon(Heroicon::ExclamationTriangle)
                ->color($downCount > 0 ? 'danger' : 'success')
                ->extraImgAttributes(['class' => $downCount > 0 ? 'animate-pulse' : '']),

            Stat::make('WP vulnerables', $degradedCount)
                ->description($degradedCount > 0 ? 'WP desactualizado' : 'Todos al día')
                ->icon(Heroicon::ShieldExclamation)
                ->color($degradedCount > 0 ? 'warning' : 'success'),

            Stat::make('WP crítico (red)', $redCount)
                ->description($redCount > 0 ? 'Versión vulnerable' : 'Sin criticidad')
                ->icon(Heroicon::Fire)
                ->color($redCount > 0 ? 'danger' : 'success'),

            Stat::make('Response media', $avgResponse . ' ms')
                ->description('Latencia promedio')
                ->icon(Heroicon::Bolt)
                ->color($avgResponse > 1000 ? 'warning' : 'success'),
        ];
    }
}