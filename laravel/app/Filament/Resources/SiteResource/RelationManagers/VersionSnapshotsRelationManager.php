<?php

namespace App\Filament\Resources\SiteResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historial de snapshots de versiones con pendientes (read-only en View).
 */
class VersionSnapshotsRelationManager extends RelationManager
{
    protected static string $relationship = 'versionSnapshots';

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
                    ->label('Snapshot')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('core_version')
                    ->label('WP core')
                    ->placeholder('—')
                    ->description(fn ($record) => self::pendingSummary($record->pending_json)),
                Tables\Columns\TextColumn::make('severity')
                    ->label('Severidad')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'red' => 'Core rojo',
                        'yellow' => 'Updates pendientes',
                        'green' => 'Al día',
                        default => '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'red' => 'danger',
                        'yellow' => 'warning',
                        default => 'success',
                    }),
            ])
            ->defaultSort('ts', 'desc')
            ->paginated([10, 25, 50]);
    }

    /** "3 plugins, 2 temas con update" — resumen del pending_json. */
    public static function pendingSummary(?string $pendingJson): string
    {
        if ($pendingJson === null) {
            return '';
        }

        $pending = json_decode($pendingJson, true);
        $p = count($pending['plugins'] ?? []);
        $t = count($pending['themes'] ?? []);

        if ($p === 0 && $t === 0) {
            return 'Sin pendientes';
        }

        return sprintf('%d plugin%s, %d tema%s con update', $p, $p === 1 ? '' : 's', $t, $t === 1 ? '' : 's');
    }
}