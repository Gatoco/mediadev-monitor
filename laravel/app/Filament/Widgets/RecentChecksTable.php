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

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Site::query()
                    ->with(['latestUptime', 'latestVersion'])
                    ->orderByDesc('updated_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('current_state')
                    ->badge()
                    ->color(fn (?SiteState $state) => match ($state) {
                        SiteState::WP_FULL => 'success',
                        SiteState::WP_DEGRADED => 'warning',
                        SiteState::NON_WP => 'info',
                        SiteState::DOWN => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('latest_uptime.ts')
                    ->label('Last check')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('latest_uptime.response_ms')
                    ->label('Response (ms)'),
                Tables\Columns\TextColumn::make('latest_version.severity')
                    ->label('Severity')
                    ->badge()
                    ->color(fn (?string $severity) => match ($severity) {
                        'red' => 'danger',
                        'yellow' => 'warning',
                        default => 'success',
                    }),
            ])
            ->paginated(false);
    }
}
