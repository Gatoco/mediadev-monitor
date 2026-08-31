<?php

namespace App\Filament\Resources\SiteResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historial de salud del sitio — score + resumen de tests (read-only en View).
 */
class SiteHealthSnapshotsRelationManager extends RelationManager
{
    protected static string $relationship = 'siteHealthSnapshots';

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
                Tables\Columns\TextColumn::make('score')
                    ->label('Score')
                    ->badge()
                    ->color(fn (?int $state) => $state === null ? 'gray' : ($state < 80 ? 'warning' : 'success')),
                Tables\Columns\TextColumn::make('tests')
                    ->label('Tests')
                    ->state(fn ($record) => self::testsSummary($record->tests_json)),
            ])
            ->defaultSort('ts', 'desc')
            ->paginated([10, 25, 50]);
    }

    /** "8/10 tests OK — 2 fallidos" desde tests_json. */
    public static function testsSummary(?string $testsJson): string
    {
        if ($testsJson === null) {
            return '—';
        }

        $tests = json_decode($testsJson, true);
        if (! is_array($tests)) {
            return '—';
        }

        $total = count($tests);
        $failed = count(array_filter($tests, fn ($t) => ($t['status'] ?? '') === 'error' || ! empty($t['error'] ?? null)));

        if ($total === 0) {
            return '—';
        }

        return $failed === 0
            ? sprintf('%d/%d tests OK', $total, $total)
            : sprintf('%d/%d OK — %d fallidos', $total - $failed, $total, $failed);
    }
}