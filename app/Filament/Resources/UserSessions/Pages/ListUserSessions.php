<?php

namespace App\Filament\Resources\UserSessions\Pages;

use App\Filament\Resources\UserSessions\UserSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListUserSessions extends ListRecords
{
    protected static string $resource = UserSessionResource::class;
}
