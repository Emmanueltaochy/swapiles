<?php

namespace App\Filament\Resources\FlaggedMessages\Pages;

use App\Filament\Resources\FlaggedMessages\FlaggedMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListFlaggedMessages extends ListRecords
{
    protected static string $resource = FlaggedMessageResource::class;
}
