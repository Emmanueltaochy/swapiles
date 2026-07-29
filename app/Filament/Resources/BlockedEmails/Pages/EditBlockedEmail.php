<?php

namespace App\Filament\Resources\BlockedEmails\Pages;

use App\Filament\Resources\BlockedEmails\BlockedEmailResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBlockedEmail extends EditRecord
{
    protected static string $resource = BlockedEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Débloquer'),
        ];
    }
}
