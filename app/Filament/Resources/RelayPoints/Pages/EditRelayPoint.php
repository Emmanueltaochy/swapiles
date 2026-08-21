<?php

namespace App\Filament\Resources\RelayPoints\Pages;

use App\Filament\Resources\RelayPoints\RelayPointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRelayPoint extends EditRecord
{
    protected static string $resource = RelayPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
