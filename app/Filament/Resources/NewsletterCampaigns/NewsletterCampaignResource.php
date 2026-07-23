<?php

namespace App\Filament\Resources\NewsletterCampaigns;

use App\Filament\Resources\NewsletterCampaigns\Pages\ListNewsletterCampaigns;
use App\Filament\Resources\NewsletterCampaigns\Pages\ViewNewsletterCampaign;
use App\Filament\Resources\NewsletterCampaigns\Schemas\NewsletterCampaignInfolist;
use App\Filament\Resources\NewsletterCampaigns\Tables\NewsletterCampaignsTable;
use App\Models\NewsletterCampaign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class NewsletterCampaignResource extends Resource
{
    protected static ?string $model = NewsletterCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return NewsletterCampaignsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NewsletterCampaignInfolist::configure($schema);
    }

    public static function getNavigationLabel(): string
    {
        return 'Stats Newsletter';
    }

    public static function getModelLabel(): string
    {
        return 'campagne';
    }

    public static function getPluralModelLabel(): string
    {
        return 'campagnes newsletter';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Communauté';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterCampaigns::route('/'),
            'view' => ViewNewsletterCampaign::route('/{record}'),
        ];
    }
}
