<?php

namespace App\Filament\Resources\Favorites\Pages;

use App\Filament\Resources\Favorites\FavoriteResource;
use App\Support\CsvExport;
use Filament\Resources\Pages\ListRecords;

class ListFavorites extends ListRecords
{
    protected static string $resource = FavoriteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CsvExport::action('favoris', [
                'ID' => fn ($f) => $f->id,
                'Date' => fn ($f) => $f->created_at,
                'Produit' => fn ($f) => $f->listing?->title,
                'Prix (€)' => fn ($f) => $f->listing?->price,
                'Île' => fn ($f) => $f->listing?->territoire,
                'Vendeur (propriétaire)' => fn ($f) => $f->listing?->user?->name,
                'Email vendeur' => fn ($f) => $f->listing?->user?->email,
                'Ajouté par' => fn ($f) => $f->user?->name,
                'Email membre' => fn ($f) => $f->user?->email,
            ], fn () => $this->getFilteredTableQuery()->with(['user', 'listing.user'])->get()),
        ];
    }
}
