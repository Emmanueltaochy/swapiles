<?php

namespace App\Filament\Resources\Echanges\Pages;

use App\Filament\Resources\Echanges\EchangeResource;
use App\Filament\Resources\Echanges\Widgets\BilanEchanges;
use App\Support\CsvExport;
use App\Support\Negociations;
use Filament\Resources\Pages\ListRecords;

class ListEchanges extends ListRecords
{
    protected static string $resource = EchangeResource::class;

    public function getSubheading(): ?string
    {
        return 'Qui propose quoi, en échange de quel article, et où en est l’échange.';
    }

    protected function getHeaderWidgets(): array
    {
        return [BilanEchanges::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CsvExport::action('echanges', [
                'Date' => fn ($e) => $e->created_at,
                'Article demandé' => fn ($e) => $e->listing?->title,
                'Prix affiché (€)' => fn ($e) => $e->listing?->price,
                'Vendeur' => fn ($e) => $e->seller?->name,
                'Proposé par' => fn ($e) => $e->proposer?->name,
                'Article proposé' => fn ($e) => $e->displayTitle(),
                'État de l’article proposé' => fn ($e) => $e->offered_condition,
                'Message' => fn ($e) => $e->message,
                'État' => fn ($e) => Negociations::ETATS_ECHANGE[Negociations::etatEchange($e)],
            ], fn () => $this->getFilteredTableQuery()->with(['proposer', 'seller', 'listing', 'offeredListing'])->get()),
        ];
    }
}
