<?php

namespace App\Filament\Resources\Negociations\Pages;

use App\Filament\Resources\Negociations\NegociationResource;
use App\Filament\Resources\Negociations\Widgets\BilanNegociations;
use App\Support\CsvExport;
use App\Support\Negociations;
use Filament\Resources\Pages\ListRecords;

class ListNegociations extends ListRecords
{
    protected static string $resource = NegociationResource::class;

    public function getSubheading(): ?string
    {
        return 'Qui propose combien, à qui, pour quel article, et où en est le deal.';
    }

    protected function getHeaderWidgets(): array
    {
        return [BilanNegociations::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CsvExport::action('negociations', [
                'Date' => fn ($o) => $o->created_at,
                'Article' => fn ($o) => $o->listing?->title,
                'Prix affiché (€)' => fn ($o) => $o->listing?->price,
                'Offre (€)' => fn ($o) => $o->amount,
                'Remise (%)' => fn ($o) => Negociations::remise($o),
                'Acheteur' => fn ($o) => $o->buyer?->name,
                'Vendeur' => fn ($o) => $o->seller?->name,
                'Origine' => fn ($o) => strip_tags(Negociations::ORIGINES[Negociations::origine($o)]),
                'État' => fn ($o) => Negociations::ETATS_OFFRE[Negociations::etatOffre($o)],
                'Île' => fn ($o) => $o->listing?->territoire,
            ], fn () => $this->getFilteredTableQuery()->with(['buyer', 'seller', 'listing'])->get()),
        ];
    }
}
