<?php

namespace App\Filament\Resources\PushEnvois\Pages;

use App\Filament\Resources\PushEnvois\PushEnvoiResource;
use App\Filament\Widgets\Push\PushParType;
use App\Filament\Widgets\Push\PushStats;
use Filament\Resources\Pages\ListRecords;

class ListPushEnvois extends ListRecords
{
    protected static string $resource = PushEnvoiResource::class;

    protected static ?string $title = 'Notifications envoyées';

    public function getSubheading(): ?string
    {
        return 'Toutes les notifications push : celles que vous envoyez et celles qui partent toutes seules (messages, ventes, relances…), leur sort et qui les a ouvertes.';
    }

    protected function getHeaderWidgets(): array
    {
        return [PushStats::class, PushParType::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
