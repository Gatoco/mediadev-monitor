<?php

namespace App\Filament\Resources\SiteResource\Widgets;

use App\Models\Site;
use App\Support\Uptime\UptimeHistory;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPIs del sitio individual en la cabecera de ViewSite (poll 5s).
 */
class SiteOverview extends StatsOverviewWidget
{
    public ?Site $record = null;

    protected function getStats(): array
    {
        $site = $this->record;
        $now = CarbonImmutable::now();

        $checks = $site->uptimeChecks()
            ->where('ts', '>=', $now->subDays(7))
            ->get();

        $uptime24 = UptimeHistory::percentage($checks->filter(fn ($c) => $c->ts >= $now->subDay()));
        $uptime7 = UptimeHistory::percentage($checks);

        $lastMs = $site->latestUptime?->response_ms;
        $failures = $site->consecutive_failures;

        return [
            Stat::make('Uptime 24h', $uptime24 !== null ? $uptime24 . '%' : '—')
                ->icon(Heroicon::OutlinedArrowTrendingUp)
                ->color($uptime24 !== null && $uptime24 < 99 ? 'danger' : 'success'),
            Stat::make('Uptime 7d', $uptime7 !== null ? $uptime7 . '%' : '—')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color($uptime7 !== null && $uptime7 < 99 ? 'danger' : 'success'),
            Stat::make('Última respuesta', $lastMs !== null ? $lastMs . ' ms' : '—')
                ->icon(Heroicon::OutlinedBolt)
                ->color($lastMs !== null && $lastMs > 1000 ? 'danger' : ($lastMs !== null && $lastMs > 500 ? 'warning' : 'success')),
            Stat::make('Fallos consecutivos', $failures)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color($failures > 0 ? 'danger' : 'success'),
        ];
    }
}