<?php

namespace App\Filament\Pages;

use App\Models\Site;
use Domain\SiteRegistry\SiteState;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    public function getTitle(): string
    {
        return 'Monitor';
    }

    public function getHeading(): string
    {
        return 'Mediadev Monitor';
    }

    public function getSubheading(): ?string
    {
        $total = Site::count();
        $down = Site::where('current_state', SiteState::DOWN)->count();
        $degraded = Site::where('current_state', SiteState::WP_DEGRADED)->count();

        $parts = ["{$total} sitios monitoreados"];

        if ($down > 0) {
            $parts[] = "{$down} caído(s)";
        }
        if ($degraded > 0) {
            $parts[] = "{$degraded} WP vulnerable(s)";
        }

        return implode(' · ', $parts);
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\SiteStatsOverview::class,
            \App\Filament\Widgets\PhpVersionsOverview::class,
            \App\Filament\Widgets\SeveritySummaryWidget::class,
            \App\Filament\Widgets\SiteHealthTable::class,
            \App\Filament\Widgets\StateLegend::class,
            \App\Filament\Widgets\RecentChecksTable::class,
        ];
    }

    public function getColumns(): int | array
    {
        return 2;
    }
}