<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteResource\Pages;
use App\Models\Site;
use App\Services\WordPress\WpTokenVerifier;
use BackedEnum;
use Domain\SiteRegistry\SiteState;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'Sitios';

    protected static ?string $modelLabel = 'Sitio';

    protected static ?string $pluralModelLabel = 'Sitios';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Estado actual')
                    ->icon(Heroicon::Signal)
                    ->iconColor('danger')
                    ->schema([
                        TextEntry::make('current_state')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (?SiteState $state): string => self::stateLabel($state))
                            ->color(fn (?SiteState $state) => self::stateColor($state)),
                        TextEntry::make('consecutive_failures')
                            ->label('Fallos consecutivos')
                            ->badge()
                            ->color(fn (int $state): string => $state >= 3 ? 'danger' : 'gray'),
                        TextEntry::make('latest_uptime.ts')
                            ->label('Último check')
                            ->dateTime('d-m-Y H:i:s'),
                    ])
                    ->columns(3),

                Section::make('Uptime & respuesta')
                    ->icon(Heroicon::Bolt)
                    ->iconColor('warning')
                    ->schema([
                        TextEntry::make('latest_uptime.status')
                            ->label('HTTP status')
                            ->badge()
                            ->color(fn ($state): string => $state && $state < 400 ? 'success' : 'danger'),
                        TextEntry::make('latest_uptime.response_ms')
                            ->label('Response (ms)')
                            ->suffix(' ms'),
                    ])
                    ->columns(2),

                Section::make('Versión WordPress')
                    ->icon(Heroicon::Cog6Tooth)
                    ->iconColor('info')
                    ->schema([
                        TextEntry::make('latest_version.core_version')
                            ->label('WP core'),
                        TextEntry::make('latest_version.severity')
                            ->label('Sev. WP')
                            ->badge()
                            ->color(fn (?string $severity) => match ($severity) {
                                'red' => 'danger',
                                'yellow' => 'warning',
                                default => 'success',
                            }),
                        TextEntry::make('latest_version.ts')
                            ->label('Visto')
                            ->dateTime('d-m H:i')
                            ->color(fn () => 'gray'),
                    ])
                    ->columns(3),

                Section::make('Salud & actividad')
                    ->icon(Heroicon::Heart)
                    ->iconColor('success')
                    ->schema([
                        TextEntry::make('latest_health.score')
                            ->label('Health score'),
                        TextEntry::make('latest_activity.ts')
                            ->label('Última actividad')
                            ->dateTime('d-m-Y H:i'),
                    ])
                    ->columns(2),

                Section::make('Credenciales WordPress (Application Password)')
                    ->icon(Heroicon::ShieldCheck)
                    ->iconColor('info')
                    ->schema([
                        TextEntry::make('wp_user')
                            ->label('Usuario WP')
                            ->placeholder('—'),
                        TextEntry::make('wp_token_status')
                            ->label('Estado del token')
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
                            }),
                        TextEntry::make('wp_token_checked_at')
                            ->label('Última verificación')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('wp_token_message')
                            ->label('Detalle')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Info del sitio')
                    ->icon(Heroicon::InformationCircle)
                    ->iconColor('gray')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre'),
                        TextEntry::make('url')
                            ->label('URL')
                            ->url(fn (Site $record): ?string => $record->url)
                            ->openUrlInNewTab(),
                        TextEntry::make('type')
                            ->label('Tipo')
                            ->badge()
                            ->color('gray'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('state_order', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('current_state')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?SiteState $state): string => self::stateLabel($state))
                    ->color(fn (?SiteState $state) => self::stateColor($state))
                    ->sortable(false),
                Tables\Columns\TextColumn::make('name')
                    ->label('Sitio')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Site $record): ?string => $record->url),
                Tables\Columns\TextColumn::make('consecutive_failures')
                    ->label('Fallos')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => $state >= 3 ? 'danger' : ($state > 0 ? 'warning' : 'gray'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('latest_uptime.response_ms')
                    ->label('Resp.')
                    ->suffix(' ms')
                    ->alignRight()
                    ->sortable(query: fn ($query, $direction) => $query->orderBy(
                        \App\Models\UptimeCheck::select('response_ms')
                            ->whereColumn('site_id', 'sites.id')
                            ->latest('ts')
                            ->take(1),
                        $direction
                    )),
                Tables\Columns\TextColumn::make('latest_version.severity')
                    ->label('Sev. WP')
                    ->badge()
                    ->color(fn (?string $severity) => match ($severity) {
                        'red' => 'danger',
                        'yellow' => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('php_version')
                    ->label('PHP')
                    ->badge()
                    ->color('info')
                    ->state(fn (Site $record): ?string => self::extractPhpVersion($record->latestHealth?->tests_json)),
                Tables\Columns\TextColumn::make('wp_token_status')
                    ->label('Token WP')
                    ->badge()
                    ->formatStateUsing(fn (?string $s): string => match ($s) {
                        WpTokenVerifier::VALID => 'válido',
                        WpTokenVerifier::INVALID => 'inválido',
                        WpTokenVerifier::UNREACHABLE => 'sin resp.',
                        default => '—',
                    })
                    ->color(fn (?string $s): string => match ($s) {
                        WpTokenVerifier::VALID => 'success',
                        WpTokenVerifier::INVALID => 'danger',
                        WpTokenVerifier::UNREACHABLE => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn (Site $r): ?string => $r->wp_token_message),
                Tables\Columns\TextColumn::make('latest_uptime.ts')
                    ->label('Último check')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->color(fn ($state) => 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('current_state')
                    ->label('Estado')
                    ->options(collect(SiteState::cases())->mapWithKeys(fn ($s) => [$s->value => self::stateLabel($s)])),
            ])
            ->actions([
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
                Action::make('verify_token')
                    ->label('Verificar token WP')
                    ->icon(Heroicon::ShieldCheck)
                    ->color('gray')
                    ->requiresConfirmation(false)
                    ->visible(fn (Site $record): bool => filled($record->ap_token))
                    ->action(function (Site $record): void {
                        /** @var WpTokenVerifier $verifier */
                        $verifier = app(WpTokenVerifier::class);
                        $result = $verifier->verify($record);

                        $notification = Notification::make()
                            ->title('Token WP: ' . $result['status'])
                            ->body($result['message']);

                        match ($result['status']) {
                            WpTokenVerifier::VALID => $notification->success(),
                            WpTokenVerifier::INVALID => $notification->danger(),
                            default => $notification->warning(),
                        };

                        $notification->send();
                    }),
            ])
            ->bulkActions([])
            ->paginated([10, 25, 50])
            ->striped();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Forms\Components\Section::make('Datos del sitio')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required(),
                        TextInput::make('url')
                            ->label('URL')
                            ->required()
                            ->url()
                            ->helperText('URL completa, ej: https://sitio.cl'),
                        TextInput::make('type')
                            ->label('Tipo')
                            ->helperText('auto | wp | non-wp (el clasificador lo ajusta)'),
                    ])
                    ->columns(2),

                \Filament\Forms\Components\Section::make('Credenciales WordPress (Application Password)')
                    ->description('Se usan para /wp-json/wp-site-health y actividad. Se guardan en la DB del monitor, no en el repo.')
                    ->schema([
                        TextInput::make('wp_user')
                            ->label('Usuario WP')
                            ->helperText('Ej. monitor'),
                        TextInput::make('ap_token')
                            ->label('Application Password')
                            ->password()
                            ->revealable()
                            ->helperText('Token tipo "xxxx xxxx xxxx xxxx xxxx xxxx" (espacios opcionales)')
                            ->maxLength(255)
                            // No pisar el token guardado si el campo se deja en blanco.
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->afterStateHydrated(fn () => null),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
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

    private static function stateLabel(?SiteState $state): string
    {
        return match ($state) {
            SiteState::WP_FULL => 'wp-full',
            SiteState::WP_DEGRADED => 'wp-degraded',
            SiteState::NON_WP => 'non-wp',
            SiteState::DOWN => 'down',
            SiteState::UNKNOWN => 'unknown',
            default => 'unknown',
        };
    }

    /**
     * Saca la versión de PHP del WP Site Health tests_json, si viene.
     * Formato del endpoint: grupos direct/async con label + test name.
     */
    public static function extractPhpVersion(?string $testsJson): ?string
    {
        if ($testsJson === null || $testsJson === '') {
            return null;
        }

        $decoded = json_decode($testsJson, true);
        if (!is_array($decoded)) {
            return null;
        }

        foreach (['direct', 'async'] as $group) {
            foreach ($decoded[$group] ?? [] as $test) {
                $label = strtolower((string) ($test['label'] ?? ''));
                $testName = strtolower((string) ($test['test'] ?? ''));

                if (!str_contains($label, 'php') && !str_contains($testName, 'php_version')) {
                    continue;
                }

                if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', (string) ($test['label'] ?? ''), $m)) {
                    return $m[1];
                }

                $badge = strtolower((string) ($test['badge']['label'] ?? ''));
                if (str_contains($badge, 'php') && preg_match('/(\d+\.\d+(?:\.\d+)?)/', $badge, $m)) {
                    return $m[1];
                }
            }
        }

        return null;
    }
}