<?php

namespace App\Filament\Resources\BlockedEmails\Pages;

use App\Filament\Resources\BlockedEmails\BlockedEmailResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlockedEmails extends ListRecords
{
    protected static string $resource = BlockedEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Bloquer un e-mail'),
        ];
    }
}
