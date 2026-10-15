<?php

namespace App\Filament\Resources\Favorites\Tables;

use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Favorite;
use App\Models\Listing;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FavoritesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'listing.user', 'listing.images']))
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100, 200])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Mis en favori le')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (Favorite $record) => optional($record->created_at)->diffForHumans())
                    ->sortable(),

                ImageColumn::make('listing_cover')
                    ->label('Photo')
                    ->getStateUsing(fn (Favorite $record) => \App\Support\ImageUrl::absolute($record->listing?->images->sortBy('order')->first()?->url))
                    ->checkFileExistence(false)
                    ->square()
                    ->size(48),

                TextColumn::make('listing.title')
                    ->label('Produit')
                    ->default('— (annonce supprimée)')
                    ->limit(28)
                    ->tooltip(fn (Favorite $record) => $record->listing?->title)
                    ->description(fn (Favorite $record) => $record->listing
                        ? number_format((float) $record->listing->price, 2, ',', ' ') . ' € · ' . ($record->listing->user?->name ?? '—')
                        : null)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('listing', fn (Builder $query) => $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
                    ->weight('bold')
                    ->color(fn (Favorite $record) => $record->listing_id ? 'primary' : 'gray')
                    ->url(fn (Favorite $record) => $record->listing_id
                        ? ListingResource::getUrl('view', ['record' => $record->listing_id])
                        : null),

                TextColumn::make('user.name')
                    ->label('Ajouté par')
                    ->default('—')
                    ->description(fn (Favorite $record) => $record->user?->email)
                    ->searchable(['name', 'email'])
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn (Favorite $record) => $record->user_id
                        ? UserResource::getUrl('view', ['record' => $record->user_id])
                        : null),

                TextColumn::make('origin')
                    ->label('Origine')
                    ->badge()
                    ->getStateUsing(fn (Favorite $record) => \App\Models\ListingInterest::where('buyer_id', $record->user_id)
                        ->where('listing_id', $record->listing_id)
                        ->exists() ? 'livraison' : 'favori')
                    ->formatStateUsing(fn (string $state) => $state === 'livraison' ? '📩 Livraison' : '⭐ Favori')
                    ->tooltip(fn (string $state) => $state === 'livraison' ? 'Ajouté via une demande de livraison' : 'Ajouté avec le cœur')
                    ->color(fn (string $state) => $state === 'livraison' ? 'warning' : 'gray'),

                TextColumn::make('listing.territoire')
                    ->label('Île')
                    ->badge()
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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

                SelectFilter::make('origin')
                    ->label('Origine du favori')
                    ->options([
                        'favori' => '⭐ Vrai favori (cœur)',
                        'livraison' => '📩 Demande de livraison',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $sub = fn ($q) => $q->selectRaw('1')
                            ->from('listing_interests')
                            ->whereColumn('listing_interests.buyer_id', 'favorites.user_id')
                            ->whereColumn('listing_interests.listing_id', 'favorites.listing_id');

                        return match ($data['value'] ?? null) {
                            'livraison' => $query->whereExists($sub),
                            'favori' => $query->whereNotExists($sub),
                            default => $query,
                        };
                    }),

                SelectFilter::make('listing_id')
                    ->label('Produit concerné')
                    ->relationship('listing', 'title')
                    ->searchable()
                    ->preload(),

                Filter::make('added_by')
                    ->label('Ajouté par (nom ou e-mail)')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('who')
                            ->label('Membre qui a mis en favori')
                            ->placeholder('Ex : Marie ou marie@…'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $who = trim((string) ($data['who'] ?? ''));
                        if ($who === '') {
                            return $query;
                        }

                        return $query->whereHas('user', fn (Builder $u) => $u
                            ->where('name', 'like', "%{$who}%")
                            ->orWhere('email', 'like', "%{$who}%"));
                    }),

                Filter::make('seller')
                    ->label('Vendeur du produit (nom ou e-mail)')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('who')
                            ->label('Vendeur / propriétaire')
                            ->placeholder('Ex : Paul ou paul@…'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $who = trim((string) ($data['who'] ?? ''));
                        if ($who === '') {
                            return $query;
                        }

                        return $query->whereHas('listing.user', fn (Builder $u) => $u
                            ->where('name', 'like', "%{$who}%")
                            ->orWhere('email', 'like', "%{$who}%"));
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Retirer'),
            ]);
    }
}
