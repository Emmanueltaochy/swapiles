<?php

namespace App\Filament\Resources\ParrainageEvenements\Pages;

use App\Filament\Resources\ParrainageEvenements\ParrainageEvenementResource;
use Filament\Resources\Pages\ListRecords;

class ListParrainageEvenements extends ListRecords
{
    protected static string $resource = ParrainageEvenementResource::class;

    public function getSubheading(): ?string
    {
        return 'Chaque partage, ouverture de lien, inscription, 1er achat et décision de récompense (acceptée ou refusée, avec le motif).';
    }
}
