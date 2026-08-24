<?php

namespace App\Filament\Widgets;

use App\Models\Site;
use Domain\SiteRegistry\SiteState;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SiteStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $sites = Site::query()->get();

        $byState = $sites->countBy(fn (Site $site) => $site->current_state instanceof SiteState
            ? $site->current_state->value
            : (string) $site->current_state);

        $avgResponse = (int) round(Site::query()
            ->with('latestUptime')
            ->get()
            ->avg(fn (Site $site) => $site->latestUptime?->response_ms) ?? 0);

        $redCount = Site::query()
            ->whereHas('latestVersion', fn ($q) => $q->where('severity', 'red'))
            ->count();

        return [
            Stat::make('Total sites', $sites->count()),
            Stat::make('Down', $byState->get('down', 0)),
            Stat::make('Avg response (ms)', $avgResponse),
            Stat::make('Outdated (red)', $redCount),
        ];
    }
}
