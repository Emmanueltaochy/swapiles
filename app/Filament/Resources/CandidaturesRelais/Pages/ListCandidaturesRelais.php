<?php

namespace App\Filament\Resources\CandidaturesRelais\Pages;

use App\Filament\Resources\CandidaturesRelais\CandidatureRelaisResource;
use Filament\Resources\Pages\ListRecords;

class ListCandidaturesRelais extends ListRecords
{
    protected static string $resource = CandidatureRelaisResource::class;

    public function getSubheading(): ?string
    {
        return 'Les commerçants qui veulent DEVENIR point relais (formulaire « Devenir point relais »). Appelez-les, puis « Créer le point relais ».';
    }
}
