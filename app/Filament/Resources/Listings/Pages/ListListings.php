<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\ListingResource;
use App\Support\CsvExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListListings extends ListRecords
{
    protected static string $resource = ListingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            CsvExport::action('annonces', [
                'ID' => fn ($l) => $l->id,
                'Titre' => fn ($l) => $l->title,
                'Vendeur' => fn ($l) => $l->user?->name,
                'Email vendeur' => fn ($l) => $l->user?->email,
                'Type' => fn ($l) => $l->listing_type,
                'Statut' => fn ($l) => $l->status,
                'Prix (€)' => fn ($l) => $l->price,
                'Île' => fn ($l) => $l->territoire,
                'Paiement en ligne' => fn ($l) => (bool) $l->requires_online_payment,
                'Colissimo' => fn ($l) => (bool) $l->allows_colissimo,
                'Vues' => fn ($l) => $l->views_count,
                'Publiée le' => fn ($l) => $l->created_at,
            ], fn () => $this->getFilteredTableQuery()->with('user')->get()),
        ];
    }
}
