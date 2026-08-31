<?php

namespace App\Filament\Widgets;

use App\Models\Site;
use Domain\SiteRegistry\SiteState;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Tabla live del fleet: DOWN primero, poll 5s, uptime % 24h y tendencia por sitio.
 */
class FleetStatusTable extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '5s';

    public function table(Table $table): Table
    {
        $day = now()->subDay();

        return $table
            ->query(
                Site::query()
                    ->with(['latestUptime', 'latestVersion', 'latestHealth'])
                    // Uptime 24h en 2 counts agregados (evita N+1 en el poll 5s).
                    ->withCount([
                        'uptimeChecks as up_24h' => fn ($q) => $q->where('ts', '>=', $day)->whereBetween('status', [200, 399]),
                        'uptimeChecks as total_24h' => fn ($q) => $q->where('ts', '>=', $day),
                    ])
                    ->orderByRaw("CASE current_state WHEN 'down' THEN 0 WHEN 'wp-degraded' THEN 1 WHEN 'unknown' THEN 2 ELSE 3 END")
                    ->orderBy('name')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Sitio')
                    ->searchable()
                    ->description(fn (Site $site) => parse_url($site->url, PHP_URL_HOST) ?: $site->url)
                    ->url(fn (Site $site) => $site->url, shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('current_state')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?SiteState $state) => self::stateLabel($state))
                    ->color(fn (?SiteState $state) => self::stateColor($state)),
                Tables\Columns\TextColumn::make('latest_uptime.response_ms')
                    ->label('Respuesta')
                    ->formatStateUsing(fn ($state) => $state !== null ? $state . ' ms' : '—')
                    ->color(fn ($state) => $state !== null && $state > 1000 ? 'danger' : ($state !== null && $state > 500 ? 'warning' : null)),
                Tables\Columns\TextColumn::make('uptime_24h')
                    ->label('Uptime 24h')
                    ->state(fn (Site $site) => $site->total_24h > 0
                        ? round($site->up_24h / $site->total_24h * 100, 1)
                        : null)
                    ->formatStateUsing(fn ($state) => $state !== null ? $state . '%' : '—')
                    ->color(fn ($state) => $state !== null && $state < 99 ? 'danger' : 'success')
                    ->icon(fn ($state) => $state !== null && $state < 99 ? Heroicon::OutlinedArrowTrendingDown : null),
                Tables\Columns\TextColumn::make('latest_version.severity')
                    ->label('Versiones')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::severityLabel($state))
                    ->color(fn ($state) => match ($state) {
                        'red' => 'danger',
                        'yellow' => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('latest_health.score')
                    ->label('Health')
                    ->badge()
                    ->color(fn ($state) => $state !== null && $state < 80 ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('latest_uptime.ts')
                    ->label('Último check')
                    ->since()
                    ->tooltip(fn (Site $site) => $site->latestUptime?->ts?->format('d/m/Y H:i')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('current_state')
                    ->label('Estado')
                    ->options(collect(SiteState::cases())->pluck('value', 'value')),
            ])
            ->actions([])
            ->bulkActions([])
            ->paginated(false);
    }

    public static function stateLabel(?SiteState $state): string
    {
        return match ($state) {
            SiteState::WP_FULL => 'OK',
            SiteState::WP_DEGRADED => 'Degradado',
            SiteState::NON_WP => 'No-WP',
            SiteState::DOWN => 'Caído',
            SiteState::UNKNOWN => 'Sin datos',
            default => '—',
        };
    }

    public static function stateColor(?SiteState $state): string
    {
        return match ($state) {
            SiteState::WP_FULL => 'success',
            SiteState::WP_DEGRADED => 'warning',
            SiteState::NON_WP => 'info',
            SiteState::DOWN => 'danger',
            default => 'gray',
        };
    }

    public static function severityLabel(?string $severity): string
    {
        return match ($severity) {
            'red' => 'Core rojo',
            'yellow' => 'Updates',
            'green' => 'Al día',
            default => '—',
        };
    }
}