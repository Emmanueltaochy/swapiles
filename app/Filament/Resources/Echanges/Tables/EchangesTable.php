<?php

namespace App\Filament\Resources\Echanges\Tables;

use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\ExchangeProposal;
use App\Support\ImageUrl;
use App\Support\Negociations;
use App\Support\TableauDeBord;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EchangesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Aucune proposition d’échange pour l’instant')
            ->columns([
                ImageColumn::make('photo_demande')
                    ->label('')
                    ->getStateUsing(fn (ExchangeProposal $e) => ImageUrl::absolute($e->listing?->images->sortBy('order')->first()?->url))
                    ->checkFileExistence(false)
                    ->square()
                    ->size(44),

                TextColumn::make('listing.title')
                    ->label('Article demandé')
                    ->default('— (annonce supprimée)')
                    ->limit(24)
                    ->tooltip(fn (ExchangeProposal $e) => $e->listing?->title)
                    ->description(fn (ExchangeProposal $e) => $e->listing
                        ? TableauDeBord::euros((float) $e->listing->price) . ' · à ' . ($e->seller?->name ?? '—')
                        : null)
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereHas('listing', fn ($l) => $l->where('title', 'like', "%{$s}%")))
                    ->weight('bold')
                    ->url(fn (ExchangeProposal $e) => $e->listing_id ? ListingResource::getUrl('view', ['record' => $e->listing_id]) : null),

                TextColumn::make('fleche')
                    ->label('')
                    ->getStateUsing(fn () => '⇄'),

                ImageColumn::make('photo_proposee')
                    ->label('')
                    ->getStateUsing(fn (ExchangeProposal $e) => ImageUrl::absolute($e->offered_listing_id
                        ? $e->offeredListing?->images->sortBy('order')->first()?->url
                        : $e->offered_photo_path))
                    ->checkFileExistence(false)
                    ->square()
                    ->size(44),

                TextColumn::make('propose')
                    ->label('Article proposé')
                    ->getStateUsing(fn (ExchangeProposal $e) => $e->displayTitle())
                    ->limit(24)
                    ->description(fn (ExchangeProposal $e) => collect([
                        'par ' . ($e->proposer?->name ?? '?'),
                        $e->offered_listing_id && $e->offeredListing ? TableauDeBord::euros((float) $e->offeredListing->price) : 'photo envoyée',
                        $e->offered_condition,
                    ])->filter()->implode(' · '))
                    ->url(fn (ExchangeProposal $e) => $e->offered_listing_id ? ListingResource::getUrl('view', ['record' => $e->offered_listing_id]) : null),

                TextColumn::make('etat')
                    ->label('Où en est l’échange')
                    ->badge()
                    ->getStateUsing(fn (ExchangeProposal $e) => Negociations::etatEchange($e))
                    ->formatStateUsing(fn (string $state) => Negociations::ETATS_ECHANGE[$state])
                    ->color(fn (string $state) => match ($state) {
                        'conclu' => 'success',
                        'acceptee' => 'info',
                        'en_attente' => 'warning',
                        default => 'danger',
                    }),

                TextColumn::make('proposer.name')
                    ->label('Proposé par')
                    ->default('—')
                    ->description(fn (ExchangeProposal $e) => $e->proposer?->email)
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereHas('proposer', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
                    ->url(fn (ExchangeProposal $e) => $e->proposer_id ? UserResource::getUrl('view', ['record' => $e->proposer_id]) : null),

                TextColumn::make('seller.name')
                    ->label('Vendeur')
                    ->default('—')
                    ->description(fn (ExchangeProposal $e) => $e->seller?->email)
                    ->url(fn (ExchangeProposal $e) => $e->seller_id ? UserResource::getUrl('view', ['record' => $e->seller_id]) : null)
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (ExchangeProposal $e) => $e->created_at?->diffForHumans())
                    ->sortable(),

                TextColumn::make('message')
                    ->label('Message')
                    ->limit(40)
                    ->tooltip(fn (ExchangeProposal $e) => $e->message)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('etat')
                    ->label('Où en est l’échange')
                    ->options(Negociations::ETATS_ECHANGE)
                    ->query(fn (Builder $q, array $data) => Negociations::filtrerEchanges($q, $data['value'] ?? null)),

                SelectFilter::make('periode')
                    ->label('Période')
                    ->options(['today' => 'Aujourd’hui', '7d' => '7 derniers jours', '30d' => '30 derniers jours', '3m' => '3 mois'])
                    ->query(function (Builder $q, array $data) {
                        $debut = match ($data['value'] ?? null) {
                            'today' => today(),
                            '7d' => now()->subDays(7),
                            '30d' => now()->subDays(30),
                            '3m' => now()->subMonths(3),
                            default => null,
                        };

                        return $debut ? $q->where('created_at', '>=', $debut) : $q;
                    }),

                Filter::make('membre')
                    ->label('Membre')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('qui')
                            ->label('Proposant ou vendeur (nom ou e-mail)'),
                    ])
                    ->query(function (Builder $q, array $data) {
                        $qui = trim((string) ($data['qui'] ?? ''));
                        if ($qui === '') {
                            return $q;
                        }
                        $cherche = fn ($u) => $u->where('name', 'like', "%{$qui}%")->orWhere('email', 'like', "%{$qui}%");

                        return $q->where(fn ($w) => $w->whereHas('proposer', $cherche)->orWhereHas('seller', $cherche));
                    }),
            ]);
    }
}
