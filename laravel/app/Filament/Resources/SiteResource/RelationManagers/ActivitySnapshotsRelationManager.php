<?php

namespace App\Filament\Resources\SiteResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historial de actividad — últimos posts capturados (read-only en View).
 */
class ActivitySnapshotsRelationManager extends RelationManager
{
    protected static string $relationship = 'activitySnapshots';

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
                Tables\Columns\TextColumn::make('last_posts')
                    ->label('Últimas publicaciones')
                    ->state(fn ($record) => self::postsSummary($record->posts_json))
                    ->wrap(),
            ])
            ->defaultSort('ts', 'desc')
            ->paginated([10, 25, 50]);
    }

    /** Títulos + fechas de los posts del snapshot, o "endpoint no disponible". */
    public static function postsSummary(?string $postsJson): string
    {
        if ($postsJson === null) {
            return '—';
        }

        $data = json_decode($postsJson, true);
        if (! is_array($data)) {
            return '—';
        }

        if (($data['unavailable'] ?? false) === true) {
            return 'Endpoint de actividad no disponible';
        }

        $posts = $data['posts'] ?? [];
        if ($posts === []) {
            return 'Sin publicaciones';
        }

        return collect($posts)
            ->map(fn (array $p) => sprintf('%s (%s)', $p['title'] ?? '(sin título)', $p['date'] ?? 'sin fecha'))
            ->implode(' · ');
    }
}