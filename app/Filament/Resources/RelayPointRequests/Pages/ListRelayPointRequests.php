<?php

namespace App\Filament\Resources\RelayPointRequests\Pages;

use App\Filament\Resources\RelayPointRequests\RelayPointRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListRelayPointRequests extends ListRecords
{
    protected static string $resource = RelayPointRequestResource::class;

    public function getSubheading(): ?string
    {
        return 'Les membres qui voudraient un point relais plus près de chez eux. Regroupez par ville pour voir où démarcher en priorité.';
    }
}
