<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Listings\ListingResource;
use App\Models\Listing;
use App\Support\ImageUrl;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListingsRelationManager extends RelationManager
{
    protected static string $relationship = 'listings';

    protected static ?string $title = 'Annonces du membre';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-rectangle-stack';

    protected const STATUS_LABELS = [
        'published' => 'En ligne',
        'draft' => 'Masquée',
        'sold' => 'Vendue',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Annonces du membre')
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Listing $record) => ListingResource::getUrl('view', ['record' => $record]))
            ->columns([
                ImageColumn::make('cover')
                    ->label('Photo')
                    ->getStateUsing(fn (Listing $record) => ImageUrl::absolute(optional($record->images()->orderBy('order')->first())->url))
                    ->checkFileExistence(false)
                    ->square()
                    ->size(44),
                TextColumn::make('title')
                    ->label('Annonce')
                    ->weight('bold')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('price')
                    ->label('Prix')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', ' ') . ' €')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'published' => 'success',
                        'sold' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('territoire')
                    ->label('Île')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('views_count')
                    ->label('Vues')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Publiée le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->emptyStateHeading('Aucune annonce pour ce membre');
    }
}
