<?php

namespace App\Filament\Resources\FlaggedMessages;

use App\Filament\Resources\FlaggedMessages\Pages\ListFlaggedMessages;
use App\Filament\Resources\Users\UserResource;
use App\Models\Message;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Signalements de modération (Partie 1, sans IA) : messages retenus par le
 * détecteur de mots-clés — paiement hors plateforme envoyé « quand même », ou
 * échange de numéro sur les premiers messages d'une conversation.
 */
class FlaggedMessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?int $navigationSort = 21;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('flagged_at', 'desc')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('flagged_at')
                    ->label('Signalé le')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (Message $r) => optional($r->flagged_at)->diffForHumans())
                    ->sortable(),

                TextColumn::make('flag_kind')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $s) => match ($s) {
                        'payment_forced' => 'Paiement forcé',
                        'phone' => 'Numéro',
                        default => $s ?: '—',
                    })
                    ->color(fn (?string $s) => $s === 'payment_forced' ? 'danger' : 'warning'),

                TextColumn::make('sender.name')
                    ->label('Expéditeur')
                    ->default('—')
                    ->description(fn (Message $r) => $r->sender?->email)
                    ->weight('bold')
                    ->color('primary')
                    ->searchable()
                    ->url(fn (Message $r) => $r->sender_id ? UserResource::getUrl('view', ['record' => $r->sender_id]) : null),

                TextColumn::make('receiver.name')
                    ->label('Destinataire')
                    ->default('—')
                    ->description(fn (Message $r) => $r->receiver?->email)
                    ->color('primary')
                    ->searchable()
                    ->url(fn (Message $r) => $r->receiver_id ? UserResource::getUrl('view', ['record' => $r->receiver_id]) : null),

                TextColumn::make('flag_reason')
                    ->label('Motif')
                    ->default('—')
                    ->wrap()
                    ->limit(60),

                TextColumn::make('body')
                    ->label('Message')
                    ->limit(60)
                    ->tooltip(fn (Message $r) => $r->body)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('listing.title')
                    ->label('Annonce')
                    ->default('— (conversation générale)')
                    ->limit(24)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('flag_kind')
                    ->label('Type de signalement')
                    ->options([
                        'payment_forced' => 'Paiement hors plateforme (forcé)',
                        'phone' => 'Échange de numéro',
                    ]),

                Filter::make('participant')
                    ->label('Participant')
                    ->form([
                        TextInput::make('who')
                            ->label('Nom ou e-mail du membre')
                            ->placeholder('Ex : Bertin ou bertin@…'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $who = trim((string) ($data['who'] ?? ''));
                        if ($who === '') {
                            return $query;
                        }

                        return $query->where(function (Builder $q) use ($who) {
                            $q->whereHas('sender', fn (Builder $s) => $s->where('name', 'like', "%{$who}%")->orWhere('email', 'like', "%{$who}%"))
                                ->orWhereHas('receiver', fn (Builder $r) => $r->where('name', 'like', "%{$who}%")->orWhere('email', 'like', "%{$who}%"));
                        });
                    }),
            ])
            ->recordActions([
                Action::make('thread')
                    ->label('Voir le fil')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('info')
                    ->modalHeading('Fil de discussion')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->modalContent(fn (Message $record) => view('filament.messages.thread', [
                        'messages' => static::threadFor($record),
                        'current' => $record,
                    ])),
            ]);
    }

    /** Tous les messages de la même conversation (mêmes participants + annonce). */
    protected static function threadFor(Message $record)
    {
        $a = $record->sender_id;
        $b = $record->receiver_id;

        return Message::query()
            ->with(['sender', 'receiver'])
            ->when(
                $record->listing_id,
                fn (Builder $q) => $q->where('listing_id', $record->listing_id),
                fn (Builder $q) => $q->whereNull('listing_id'),
            )
            ->where(function (Builder $q) use ($a, $b) {
                $q->where(fn (Builder $x) => $x->where('sender_id', $a)->where('receiver_id', $b))
                    ->orWhere(fn (Builder $x) => $x->where('sender_id', $b)->where('receiver_id', $a));
            })
            ->orderBy('created_at')
            ->limit(200)
            ->get();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotNull('flagged_at')
            ->with(['sender', 'receiver', 'listing']);
    }

    public static function getNavigationLabel(): string
    {
        return 'Signalements';
    }

    public static function getModelLabel(): string
    {
        return 'signalement';
    }

    public static function getPluralModelLabel(): string
    {
        return 'signalements';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Communauté';
    }

    /** Pastille : nombre de paiements forcés (les plus sensibles) sur 30 jours. */
    public static function getNavigationBadge(): ?string
    {
        $count = Message::query()
            ->where('flag_kind', 'payment_forced')
            ->where('flagged_at', '>=', now()->subDays(30))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlaggedMessages::route('/'),
        ];
    }
}
