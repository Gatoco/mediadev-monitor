<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SiteResource;
use App\Models\Site;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Panorama de PHP: qué versiones de PHP corren los sitios WP del portfolio,
 * extraídas del Site Health endpoint (tests_json).
 *
 * Silencioso: si ningún sitio tiene AP/token, muestra "sin datos" en vez de ceros.
 */
class PhpVersionsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '300s';

    protected function getStats(): array
    {
        /** @var Collection<int, Site> $sites */
        $sites = Site::query()
            ->with(['latestHealth'])
            ->get();

        $phpVersions = [];
        $outdated = []; // PHP < 8.0 (EOL oficial)
        $noData = 0;

        foreach ($sites as $site) {
            $php = SiteResource::extractPhpVersion($site->latestHealth?->tests_json);
            if ($php === null) {
                $noData++;
                continue;
            }

            $phpVersions[$php] = ($phpVersions[$php] ?? 0) + 1;

            if (version_compare($php, '8.0', '<')) {
                $outdated[$site->name] = $php;
            }
        }

        krsort($phpVersions);

        if ($phpVersions === []) {
            return [
                Stat::make('PHP del portfolio', 'Sin datos')
                    ->description('Cargue el Application Password del WP en cada sitio para ver la versión de PHP')
                    ->icon(Heroicon::InformationCircle)
                    ->color('gray'),
            ];
        }

        $latest = array_key_first($phpVersions);
        $coverage = $sites->count() - $noData;

        return [
            Stat::make('PHP más reciente', 'PHP ' . $latest)
                ->description("{$phpVersions[$latest]} sitio(s)")
                ->icon(Heroicon::ArrowTrendingUp)
                ->color('success'),

            Stat::make('PHP antiguos (< 8.0)', count($outdated))
                ->description($outdated !== []
                    ? implode(' · ', array_map(static fn ($n, $p) => "{$n} ({$p})", array_keys($outdated), $outdated))
                    : 'Todos al día')
                ->icon(Heroicon::ExclamationTriangle)
                ->color(count($outdated) > 0 ? 'danger' : 'success'),

            Stat::make('Cobertura Site Health', $coverage . '/' . $sites->count())
                ->description('Sitios con token WP cargado')
                ->icon(Heroicon::ServerStack)
                ->color('gray'),
        ];
    }
}
