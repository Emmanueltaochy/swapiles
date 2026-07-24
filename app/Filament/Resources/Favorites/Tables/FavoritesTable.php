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
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100, 200])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Mis en favori le')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (Favorite $record) => optional($record->created_at)->diffForHumans())
                    ->sortable(),

                ImageColumn::make('listing_cover')
                    ->label('Photo')
                    ->getStateUsing(fn (Favorite $record) => \App\Support\ImageUrl::absolute(optional($record->listing?->images()->orderBy('order')->first())->url))
                    ->checkFileExistence(false)
                    ->square()
                    ->size(48),

                TextColumn::make('listing.title')
                    ->label('Produit')
                    ->default('— (annonce supprimée)')
                    ->limit(32)
                    ->tooltip(fn (Favorite $record) => $record->listing?->title)
                    ->searchable()
                    ->weight('bold')
                    ->color(fn (Favorite $record) => $record->listing_id ? 'primary' : 'gray')
                    ->url(fn (Favorite $record) => $record->listing_id
                        ? ListingResource::getUrl('view', ['record' => $record->listing_id])
                        : null),

                TextColumn::make('listing.price')
                    ->label('Prix')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2, ',', ' ') . ' €' : '—')
                    ->sortable(),

                TextColumn::make('listing.user.name')
                    ->label('Appartient à (vendeur)')
                    ->default('—')
                    ->description(fn (Favorite $record) => $record->listing?->user?->email)
                    ->searchable()
                    ->color('primary')
                    ->url(fn (Favorite $record) => $record->listing?->user_id
                        ? UserResource::getUrl('view', ['record' => $record->listing->user_id])
                        : null),

                TextColumn::make('user.name')
                    ->label('Ajouté en favori par')
                    ->default('—')
                    ->description(fn (Favorite $record) => $record->user?->email)
                    ->searchable()
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn (Favorite $record) => $record->user_id
                        ? UserResource::getUrl('view', ['record' => $record->user_id])
                        : null),

                TextColumn::make('listing.territoire')
                    ->label('Île')
                    ->badge()
                    ->default('—')
                    ->toggleable(),
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
