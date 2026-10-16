<?php

namespace App\Filament\Resources\Negociations\Tables;

use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\ListingOffer;
use App\Support\Negociations;
use App\Support\TableauDeBord;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NegociationsTable
{
    public static function configure(Table $table): Table
    {
        $membre = fn (string $relation) => fn (ListingOffer $o) => $o->{$relation . '_id'}
            ? UserResource::getUrl('view', ['record' => $o->{$relation . '_id'}]) : null;

        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Aucune négociation pour l’instant')
            ->columns([
                ImageColumn::make('photo')
                    ->label('')
                    ->getStateUsing(fn (ListingOffer $o) => \App\Support\ImageUrl::absolute($o->listing?->images->sortBy('order')->first()?->url))
                    ->checkFileExistence(false)
                    ->square()
                    ->size(44),

                TextColumn::make('listing.title')
                    ->label('Article')
                    ->default('— (annonce supprimée)')
                    ->limit(26)
                    ->tooltip(fn (ListingOffer $o) => $o->listing?->title)
                    ->description(fn (ListingOffer $o) => $o->listing
                        ? 'affiché ' . TableauDeBord::euros((float) $o->listing->price) . ' · ' . ($o->listing->territoire ?: '—')
                        : null)
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereHas('listing', fn ($l) => $l->where('title', 'like', "%{$s}%")))
                    ->weight('bold')
                    ->url(fn (ListingOffer $o) => $o->listing_id ? ListingResource::getUrl('view', ['record' => $o->listing_id]) : null),

                TextColumn::make('amount')
                    ->label('Offre')
                    ->formatStateUsing(fn ($state) => TableauDeBord::euros((float) $state))
                    ->description(fn (ListingOffer $o) => collect([
                        ($r = Negociations::remise($o)) !== null ? ($r > 0 ? '−' . $r . ' %' : 'prix affiché') : null,
                        ($o->buyer?->name ?? '?') . ' → ' . ($o->seller?->name ?? '?'),
                    ])->filter()->implode(' · '))
                    ->weight('bold')
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('etat')
                    ->label('Où en est le deal')
                    ->badge()
                    ->getStateUsing(fn (ListingOffer $o) => Negociations::etatOffre($o))
                    ->formatStateUsing(fn (string $state) => Negociations::ETATS_OFFRE[$state])
                    ->description(fn (ListingOffer $o) => $o->expire_at && $o->status === 'accepted' && $o->expire_at->isFuture()
                        ? 'valable jusqu’au ' . $o->expire_at->format('d/m H:i') : null)
                    ->color(fn (string $state) => match ($state) {
                        'payee' => 'success',
                        'acceptee' => 'info',
                        'en_attente' => 'warning',
                        'refusee' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('buyer.name')
                    ->label('Acheteur')
                    ->default('—')
                    ->description(fn (ListingOffer $o) => $o->buyer?->email)
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereHas('buyer', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
                    ->url($membre('buyer')),

                TextColumn::make('seller.name')
                    ->label('Vendeur')
                    ->default('—')
                    ->description(fn (ListingOffer $o) => $o->seller?->email)
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereHas('seller', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
                    ->url($membre('seller')),

                TextColumn::make('origine')
                    ->label('Qui propose')
                    ->badge()
                    ->getStateUsing(fn (ListingOffer $o) => Negociations::origine($o))
                    ->formatStateUsing(fn (string $state) => Negociations::ORIGINES[$state])
                    ->color(fn (string $state) => match ($state) {
                        'favoris' => 'info',
                        'contre_offre' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (ListingOffer $o) => $o->created_at?->diffForHumans())
                    ->sortable(),

                TextColumn::make('message')
                    ->label('Message')
                    ->limit(40)
                    ->tooltip(fn (ListingOffer $o) => $o->message)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('etat')
                    ->label('Où en est le deal')
                    ->options(Negociations::ETATS_OFFRE)
                    ->query(fn (Builder $q, array $data) => Negociations::filtrerOffres($q, $data['value'] ?? null)),

                SelectFilter::make('origine')
                    ->label('Qui propose')
                    ->options(Negociations::ORIGINES)
                    ->query(fn (Builder $q, array $data) => Negociations::filtrerOrigine($q, $data['value'] ?? null)),

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

                SelectFilter::make('ile')
                    ->label('Île')
                    ->options(fn () => \App\Models\Listing::query()->whereNotNull('territoire')->where('territoire', '!=', '')
                        ->distinct()->orderBy('territoire')->pluck('territoire', 'territoire')->all())
                    ->query(fn (Builder $q, array $data) => filled($data['value'] ?? null)
                        ? $q->whereHas('listing', fn ($l) => $l->where('territoire', $data['value'])) : $q),

                Filter::make('membre')
                    ->label('Membre')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('qui')
                            ->label('Acheteur ou vendeur (nom ou e-mail)'),
                    ])
                    ->query(function (Builder $q, array $data) {
                        $qui = trim((string) ($data['qui'] ?? ''));
                        if ($qui === '') {
                            return $q;
                        }
                        $cherche = fn ($u) => $u->where('name', 'like', "%{$qui}%")->orWhere('email', 'like', "%{$qui}%");

                        return $q->where(fn ($w) => $w->whereHas('buyer', $cherche)->orWhereHas('seller', $cherche));
                    }),
            ]);
    }
}
