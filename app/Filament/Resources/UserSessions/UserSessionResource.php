<?php

namespace App\Filament\Resources\UserSessions;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\UserSessions\Pages\ListUserSessions;
use App\Models\User;
use App\Models\UserSession;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserSessionResource extends Resource
{
    protected static ?string $model = UserSession::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static ?int $navigationSort = 12;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100, 200])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Quand')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Compte')
                    ->default('— (compte supprimé)')
                    ->description(fn (UserSession $r) => $r->user?->email)
                    ->weight('bold')
                    ->color('primary')
                    ->searchable()
                    ->url(fn (UserSession $r) => $r->user_id ? UserResource::getUrl('view', ['record' => $r->user_id]) : null),
                TextColumn::make('event_type')
                    ->label('Événement')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'registration' ? 'Inscription' : 'Connexion')
                    ->color(fn (string $state) => $state === 'registration' ? 'warning' : 'gray'),
                TextColumn::make('ip')
                    ->label('IP')
                    ->default('—')
                    ->badge()
                    ->color('info')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('device_fingerprint')
                    ->label('Empreinte appareil')
                    ->default('—')
                    ->limit(14)
                    ->tooltip(fn (UserSession $r) => $r->device_fingerprint)
                    ->copyable()
                    ->searchable(),
                TextColumn::make('user_agent')
                    ->label('Appareil / navigateur')
                    ->limit(32)
                    ->tooltip(fn (UserSession $r) => $r->user_agent)
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->label('Événement')
                    ->options([
                        'registration' => 'Inscription',
                        'login' => 'Connexion',
                    ]),
            ])
            ->recordActions([
                Action::make('linked')
                    ->label('Comptes liés')
                    ->icon('heroicon-o-users')
                    ->color('danger')
                    ->modalHeading('Comptes liés (même IP ou même appareil)')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->visible(fn (UserSession $r) => $r->user_id !== null)
                    ->modalContent(fn (UserSession $r) => view('filament.user-sessions.linked', [
                        'source' => $r->user,
                        'linked' => User::whereIn('id', UserSession::linkedUserIds((int) $r->user_id))
                            ->withCount('listings')
                            ->get(),
                    ])),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function getNavigationLabel(): string
    {
        return 'Connexions';
    }

    public static function getModelLabel(): string
    {
        return 'connexion';
    }

    public static function getPluralModelLabel(): string
    {
        return 'connexions';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Communauté';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserSessions::route('/'),
        ];
    }
}
