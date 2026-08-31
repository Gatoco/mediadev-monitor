<?php

namespace App\Filament\Resources\SiteResource\RelationManagers;

use App\Support\Uptime\UptimeHistory;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historial de checks de uptime (read-only en View).
 */
class UptimeChecksRelationManager extends RelationManager
{
    protected static string $relationship = 'uptimeChecks';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('ts')
                    ->label('Check')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('HTTP')
                    ->badge()
                    ->color(fn (?int $state) => $state === null || ! UptimeHistory::isUp($state) ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('response_ms')
                    ->label('Respuesta')
                    ->formatStateUsing(fn ($state) => $state !== null ? $state . ' ms' : '—')
                    ->color(fn ($state) => $state !== null && $state > 1000 ? 'danger' : ($state !== null && $state > 500 ? 'warning' : null)),
                Tables\Columns\TextColumn::make('tls_state')
                    ->label('TLS')
                    ->placeholder('—'),
            ])
            ->defaultSort('ts', 'desc')
            ->paginated([10, 25, 50]);
    }
}