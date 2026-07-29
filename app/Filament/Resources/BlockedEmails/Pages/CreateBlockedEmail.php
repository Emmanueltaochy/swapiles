<?php

namespace App\Filament\Resources\BlockedEmails\Pages;

use App\Filament\Resources\BlockedEmails\BlockedEmailResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlockedEmail extends CreateRecord
{
    protected static string $resource = BlockedEmailResource::class;
}
