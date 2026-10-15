<?php

namespace App\Filament\Resources\LiveSessions\Pages;

use App\Filament\Resources\LiveSessions\LiveSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListLiveSessions extends ListRecords
{
    protected static string $resource = LiveSessionResource::class;

    public function getSubheading(): ?string
    {
        return config('live.ouvert_a_tous')
            ? 'Ouvert à tous les membres.'
            : '🧪 Mode test : le live n’est visible que par les administrateurs (et les testeurs de LIVE_TESTEURS).';
    }
}
