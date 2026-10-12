<?php

namespace App\Filament\Widgets\Tableau;

use App\Filament\Resources\Listings\ListingResource;
use App\Models\Listing;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopAnnonces extends TableWidget
{
    protected static ?int $sort = 9;

    protected static ?string $heading = 'Annonces les plus vues';


    protected static bool $isDiscovered = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(Listing::query()->with('user')->withCount('favoritedBy')->where('status', 'published')->orderByDesc('views_count')->limit(8))
            ->paginated(false)
            ->recordUrl(fn (Listing $l) => ListingResource::getUrl('view', ['record' => $l]))
            ->columns([
                TextColumn::make('title')->label('Annonce')->limit(28)
                    ->description(fn (Listing $l) => ($l->user->name ?? '—') . ' · ' . ($l->territoire ?: '—')),
                TextColumn::make('price')->label('Prix')->money('EUR', locale: 'fr'),
                TextColumn::make('views_count')->label('Vues'),
                TextColumn::make('favorited_by_count')->label('Favoris'),
            ]);
    }
}
