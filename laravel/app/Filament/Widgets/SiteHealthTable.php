<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SiteResource;
use App\Models\Site;
use App\Services\WordPress\WpTokenVerifier;
use Domain\SiteRegistry\SiteState;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Panel técnico por sitio: PHP, WP core, salud, AP, último check.
 *
 * Complementa PhpVersionsOverview (que agrega) con el detalle por fila.
 * Útil para el día a día: pillar de una mirada qué sitio se quedó atrás
 * en PHP, cuál tiene el token caído, cuál hace cuánto no reporta health.
 */
class SiteHealthTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '300s';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Detalle técnico por sitio')
            ->query(
                Site::query()->with(['latestUptime', 'latestVersion', 'latestHealth'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Sitio')
                    ->weight('medium')
                    ->description(fn (Site $r): ?string => $r->url)
                    ->searchable(),

                Tables\Columns\TextColumn::make('current_state')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?SiteState $s) => $s?->value ?? 'unknown')
                    ->color(fn (?SiteState $s): string => match ($s) {
                        SiteState::WP_FULL => 'success',
                        SiteState::WP_DEGRADED => 'warning',
                        SiteState::NON_WP => 'info',
                        SiteState::DOWN => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('php')
                    ->label('PHP')
                    ->state(fn (Site $r): ?string => SiteResource::extractPhpVersion($r->latestHealth?->tests_json))
                    ->badge()
                    ->color(fn (?string $state): string => match (true) {
                        $state === null => 'gray',
                        version_compare($state, '8.0', '<') => 'danger',
                        version_compare($state, '8.2', '<') => 'warning',
                        default => 'success',
                    })
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('latest_version.core_version')
                    ->label('WP core')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('latest_version.severity')
                    ->label('Sev.')
                    ->badge()
                    ->color(fn (?string $s): string => match ($s) {
                        'red' => 'danger',
                        'yellow' => 'warning',
                        default => 'success',
                    })
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('latest_health.score')
                    ->label('Health')
                    ->suffix('%')
                    ->alignRight()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('wp_token_status')
                    ->label('Token WP')
                    ->badge()
                    ->formatStateUsing(fn (?string $s): string => match ($s) {
                        WpTokenVerifier::VALID => 'válido',
                        WpTokenVerifier::INVALID => 'inválido',
                        WpTokenVerifier::UNREACHABLE => 'sin respuesta',
                        default => 'sin verificar',
                    })
                    ->color(fn (?string $s): string => match ($s) {
                        WpTokenVerifier::VALID => 'success',
                        WpTokenVerifier::INVALID => 'danger',
                        WpTokenVerifier::UNREACHABLE => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn (Site $r): ?string => $r->wp_token_message),

                Tables\Columns\TextColumn::make('latest_uptime.response_ms')
                    ->label('Resp.')
                    ->suffix(' ms')
                    ->alignRight()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('latest_uptime.ts')
                    ->label('Últ. check')
                    ->dateTime('H:i')
                    ->color('gray')
                    ->placeholder('—'),
            ])
            ->paginated(false);
    }
}
