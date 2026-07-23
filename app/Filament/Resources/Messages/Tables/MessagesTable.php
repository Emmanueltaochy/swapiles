<?php

namespace App\Filament\Resources\Messages\Tables;

use App\Filament\Resources\Users\UserResource;
use App\Models\Message;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100, 200])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Quand')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (Message $record) => optional($record->created_at)->diffForHumans())
                    ->sortable(),

                TextColumn::make('sender.name')
                    ->label('Expéditeur')
                    ->default('—')
                    ->description(fn (Message $record) => $record->sender?->email)
                    ->searchable()
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn (Message $record) => $record->sender_id ? UserResource::getUrl('view', ['record' => $record->sender_id]) : null),

                TextColumn::make('receiver.name')
                    ->label('Destinataire')
                    ->default('—')
                    ->description(fn (Message $record) => $record->receiver?->email)
                    ->searchable()
                    ->color('primary')
                    ->url(fn (Message $record) => $record->receiver_id ? UserResource::getUrl('view', ['record' => $record->receiver_id]) : null),

                TextColumn::make('listing.title')
                    ->label('Annonce')
                    ->default('— (conversation générale)')
                    ->limit(30)
                    ->tooltip(fn (Message $record) => $record->listing?->title)
                    ->searchable()
                    ->color(fn (Message $record) => $record->listing_id ? 'primary' : 'gray')
                    ->url(fn (Message $record) => $record->listing_id
                        ? \App\Filament\Resources\Listings\ListingResource::getUrl('view', ['record' => $record->listing_id])
                        : null),

                TextColumn::make('body')
                    ->label('Message')
                    ->limit(60)
                    ->tooltip(fn (Message $record) => $record->body)
                    ->searchable()
                    ->wrap(),

                IconColumn::make('read_at')
                    ->label('Lu')
                    ->boolean()
                    ->getStateUsing(fn (Message $record) => ! is_null($record->read_at)),
            ])
            ->filters([
                TernaryFilter::make('unread')
                    ->label('Lecture')
                    ->placeholder('Tous')
                    ->trueLabel('Non lus uniquement')
                    ->falseLabel('Lus uniquement')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('read_at'),
                        false: fn (Builder $query) => $query->whereNotNull('read_at'),
                        blank: fn (Builder $query) => $query,
                    ),

                SelectFilter::make('range')
                    ->label('Période')
                    ->options([
                        'today' => 'Aujourd’hui',
                        '7d' => '7 derniers jours',
                        '30d' => '30 derniers jours',
                        '3m' => '3 mois',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $start = match ($data['value'] ?? null) {
                            'today' => today(),
                            '7d' => now()->subDays(7),
                            '30d' => now()->subDays(30),
                            '3m' => now()->subMonths(3),
                            default => null,
                        };

                        return $start ? $query->where('created_at', '>=', $start) : $query;
                    }),

                SelectFilter::make('listing_id')
                    ->label('Annonce concernée')
                    ->relationship('listing', 'title')
                    ->searchable()
                    ->preload(),

                Filter::make('participant')
                    ->label('Participant (nom ou e-mail)')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('who')
                            ->label('Nom ou e-mail du membre')
                            ->placeholder('Ex : Marie ou marie@…'),
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

                DeleteAction::make()
                    ->label('Supprimer'),
            ]);
    }

    /** Récupère tous les messages de la même conversation (mêmes participants + même annonce). */
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
}
