<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteResource\Pages;
use App\Models\Site;
use BackedEnum;
use Domain\SiteRegistry\SiteState;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-server-stack';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Site')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('url'),
                        TextEntry::make('current_state')
                            ->label('State')
                            ->badge()
                            ->color(fn (?SiteState $state) => self::stateColor($state)),
                        TextEntry::make('type'),
                        TextEntry::make('consecutive_failures'),
                        TextEntry::make('latest_uptime.status')
                            ->label('Last uptime HTTP status'),
                        TextEntry::make('latest_uptime.response_ms')
                            ->label('Response (ms)'),
                        TextEntry::make('latest_version.core_version')
                            ->label('WP core'),
                        TextEntry::make('latest_version.severity')
                            ->label('Version severity')
                            ->badge()
                            ->color(fn (?string $severity) => match ($severity) {
                                'red' => 'danger',
                                'yellow' => 'warning',
                                default => 'success',
                            }),
                        TextEntry::make('latest_health.score')
                            ->label('Health score'),
                        TextEntry::make('latest_activity.ts')
                            ->label('Last activity')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('url')
                    ->searchable(),
                Tables\Columns\TextColumn::make('current_state')
                    ->badge()
                    ->color(fn (?SiteState $state) => self::stateColor($state)),
                Tables\Columns\TextColumn::make('consecutive_failures')
                    ->sortable(),
                Tables\Columns\TextColumn::make('latest_uptime.ts')
                    ->label('Last check')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('current_state')
                    ->options(collect(SiteState::cases())->pluck('value', 'value')),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->required(),
                TextInput::make('url')
                    ->required()
                    ->url(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['latestUptime', 'latestVersion', 'latestHealth', 'latestActivity']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSites::route('/'),
            'view' => Pages\ViewSite::route('/{record}'),
            'edit' => Pages\EditSite::route('/{record}/edit'),
        ];
    }

    private static function stateColor(?SiteState $state): string
    {
        return match ($state) {
            SiteState::WP_FULL => 'success',
            SiteState::WP_DEGRADED => 'warning',
            SiteState::NON_WP => 'info',
            SiteState::DOWN => 'danger',
            default => 'gray',
        };
    }
}
