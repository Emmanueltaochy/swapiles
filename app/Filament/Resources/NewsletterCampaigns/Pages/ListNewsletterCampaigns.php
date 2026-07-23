<?php

namespace App\Filament\Resources\NewsletterCampaigns\Pages;

use App\Filament\Resources\NewsletterCampaigns\NewsletterCampaignResource;
use App\Filament\Widgets\NewsletterOverview;
use Filament\Resources\Pages\ListRecords;

class ListNewsletterCampaigns extends ListRecords
{
    protected static string $resource = NewsletterCampaignResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            NewsletterOverview::class,
        ];
    }
}
