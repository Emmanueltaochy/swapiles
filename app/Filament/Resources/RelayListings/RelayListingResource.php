<?php

namespace App\Filament\Resources\RelayListings;

use App\Filament\Resources\RelayListings\Pages\ListRelayListings;
use App\Models\Listing;
use App\Models\RelayPoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vue admin : les annonces (produits) pour lesquelles un point relais a été
 * retenu — soit explicitement sur l'annonce, soit via le réglage par défaut du
 * vendeur. Filtrable par point de vente pour voir tout ce qui y est rattaché.
 */
class RelayListingResource extends Resource
{
    protected static ?string $model = Listing::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?int $navigationSort = 31;

    public static function getEloquentQuery(): Builder
    {
        // Uniquement les annonces CB (le relais est réservé au paiement CB) qui
        // ont un relais retenu : surcharge annonce OU défaut du vendeur.
        return parent::getEloquentQuery()
            ->where('requires_online_payment', true)
            ->where(function (Builder $q) {
                $q->whereHas('relayPoints')
                    ->orWhereHas('user.acceptedRelayPoints');
            })
            ->with(['user', 'relayPoints']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('title')
                    ->label('Produit')
                    ->weight('bold')
                    ->limit(40)
                    ->searchable()
                    ->url(fn (Listing $r) => route('listings.show', $r), shouldOpenInNewTab: true),

                TextColumn::make('user.name')
                    ->label('Vendeur')
                    ->default('—')
                    ->description(fn (Listing $r) => $r->user?->email)
                    ->searchable(),

                TextColumn::make('territoire')
                    ->label('Île')
                    ->badge()
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Prix')
                    ->money('eur')
                    ->sortable(),

                TextColumn::make('relais')
                    ->label('Points relais retenus')
                    ->badge()
                    ->getStateUsing(fn (Listing $r) => $r->selectedRelayPoints()->pluck('name')->all() ?: ['—'])
                    ->color('info'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (?string $s) => match ($s) {
                        'published' => 'En ligne',
                        'sold' => 'Vendu',
                        default => $s ?: '—',
                    })
                    ->color(fn (?string $s) => $s === 'sold' ? 'gray' : 'success')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Publié le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('relay')
                    ->label('Point de vente')
                    ->options(fn () => RelayPoint::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data) {
                        $relayId = $data['value'] ?? null;
                        if (! $relayId) {
                            return $query;
                        }

                        // Annonce retenue pour ce relais = surcharge annonce contenant
                        // ce relais, OU (aucune surcharge ET défaut vendeur le contenant).
                        return $query->where(function (Builder $q) use ($relayId) {
                            $q->whereHas('relayPoints', fn (Builder $r) => $r->where('relay_points.id', $relayId))
                                ->orWhere(function (Builder $q2) use ($relayId) {
                                    $q2->doesntHave('relayPoints')
                                        ->whereHas('user.acceptedRelayPoints', fn (Builder $r) => $r->where('relay_points.id', $relayId));
                                });
                        });
                    }),

                SelectFilter::make('territoire')
                    ->label('Île')
                    ->options(\App\Support\DomTomGeo::territoires()
                        ? array_combine(\App\Support\DomTomGeo::territoires(), \App\Support\DomTomGeo::territoires())
                        : []),
            ]);
    }

    public static function getNavigationLabel(): string
    {
        return 'Produits par relais';
    }

    public static function getModelLabel(): string
    {
        return 'annonce point relais';
    }

    public static function getPluralModelLabel(): string
    {
        return 'annonces point relais';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketplace';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRelayListings::route('/'),
        ];
    }
}
