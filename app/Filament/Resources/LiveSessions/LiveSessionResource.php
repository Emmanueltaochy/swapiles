<?php

namespace App\Filament\Resources\LiveSessions;

use App\Filament\Resources\LiveSessions\Pages\ListLiveSessions;
use App\Models\LiveSession;
use App\Support\LiveService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Suivi et modération des ventes en direct. */
class LiveSessionResource extends Resource
{
    protected static ?string $model = LiveSession::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationLabel = 'Ventes en direct';

    protected static ?string $modelLabel = 'live';

    protected static ?string $pluralModelLabel = 'lives';

    protected static string | \UnitEnum | null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('titre')->label('Live')->weight('bold')->searchable()
                    ->url(fn (LiveSession $r) => route('live.show', $r), shouldOpenInNewTab: true),
                TextColumn::make('vendeur.name')->label('Vendeur'),
                TextColumn::make('statut')->label('Statut')->badge()
                    ->formatStateUsing(fn (?string $state) => LiveSession::STATUTS[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) { 'en_direct' => 'danger', 'programme' => 'info', default => 'gray' }),
                TextColumn::make('debut_prevu_at')->label('Prévu le')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('spectateurs_max')->label('Spectateurs max'),
                TextColumn::make('messages_count')->counts('messages')->label('Messages'),
                TextColumn::make('coupe_raison')->label('Coupé')->placeholder('—')->color('danger'),
            ])
            ->recordActions([
                Action::make('couper')
                    ->label('Couper le live')
                    ->icon('heroicon-o-stop-circle')
                    ->color('danger')
                    ->visible(fn (LiveSession $r) => $r->statut !== 'termine')
                    ->schema([TextInput::make('raison')->label('Raison (visible dans l’admin)')->required()->maxLength(255)])
                    ->action(function (LiveSession $r, array $data) {
                        $r->update(['statut' => 'termine', 'termine_at' => now(), 'coupe_raison' => $data['raison'], 'article_epingle_id' => null]);
                        \App\Support\LiveEnregistrement::arreter($r);
                        LiveService::message($r, null, 'Ce live a été interrompu par l’équipe Swap’Îles.', 'systeme');
                    }),
                Action::make('supprimerReplay')
                    ->label('Supprimer le replay')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (LiveSession $r) => $r->replayUrl() !== null)
                    ->requiresConfirmation()
                    ->modalDescription('La vidéo est retirée du site et effacée du stockage. Action définitive.')
                    ->action(fn (LiveSession $r) => \App\Support\LiveEnregistrement::supprimerReplay($r)),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListLiveSessions::route('/')];
    }
}
