<?php

namespace App\Filament\Resources\RelayPoints\Pages;

use App\Filament\Resources\RelayPoints\RelayPointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRelayPoints extends ListRecords
{
    protected static string $resource = RelayPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Ajouter un point relais'),
        ];
    }
}
