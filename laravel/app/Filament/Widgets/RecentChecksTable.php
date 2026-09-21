<?php

namespace App\Filament\Widgets;

use App\Models\Site;
use Domain\SiteRegistry\SiteState;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentChecksTable extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Estado por sitio')
            ->description('Prioridad: caídos · WP vulnerables · resto')
            ->query(
                Site::query()
                    ->with(['latestUptime', 'latestVersion'])
                    ->selectRaw('sites.*, CASE
                        WHEN current_state = ? THEN 0
                        WHEN current_state = ? THEN 1
                        WHEN current_state = ? THEN 2
                        WHEN current_state = ? THEN 3
                        ELSE 4
                    END AS state_order', [
                        SiteState::DOWN->value,
                        SiteState::WP_DEGRADED->value,
                        SiteState::WP_FULL->value,
                        SiteState::NON_WP->value,
                    ])
                    ->orderBy('state_order')
                    ->orderBy('name')
            )
            ->columns([
                Tables\Columns\TextColumn::make('current_state')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (?SiteState $state) => match ($state) {
                        SiteState::DOWN => 'danger',
                        SiteState::WP_DEGRADED => 'warning',
                        SiteState::WP_FULL => 'success',
                        SiteState::NON_WP => 'info',
                        default => 'gray',
                    })
                    ->sortable(false),
                Tables\Columns\TextColumn::make('name')
                    ->label('Sitio')
                    ->searchable()
                    ->weight('medium')
                    ->sortable(),
                Tables\Columns\TextColumn::make('latest_uptime.response_ms')
                    ->label('Resp.')
                    ->suffix(' ms')
                    ->alignRight()
                    ->color(fn ($state) => 'gray'),
                Tables\Columns\TextColumn::make('latest_version.severity')
                    ->label('Sev.')
                    ->badge()
                    ->color(fn (?string $severity) => match ($severity) {
                        'red' => 'danger',
                        'yellow' => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('latest_uptime.ts')
                    ->label('Último check')
                    ->dateTime('d-m-Y H:i')
                    ->color(fn ($state) => 'gray'),
            ])
            ->paginated(false);
    }
}