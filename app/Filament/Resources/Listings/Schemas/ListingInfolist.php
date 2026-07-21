<?php

namespace App\Filament\Resources\Listings\Schemas;

use App\Models\Listing;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ListingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('sharetribe_id')
                    ->placeholder('-'),
                TextEntry::make('user_id')
                    ->numeric(),
                TextEntry::make('title'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('price')
                    ->money('EUR'),
                TextEntry::make('currency'),
                TextEntry::make('listing_type')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('territoire'),
                TextEntry::make('category_level1')
                    ->placeholder('-'),
                TextEntry::make('category_level2')
                    ->placeholder('-'),
                TextEntry::make('category_level3')
                    ->placeholder('-'),
                TextEntry::make('etat')
                    ->placeholder('-'),
                TextEntry::make('marque')
                    ->placeholder('-'),
                TextEntry::make('taille')
                    ->placeholder('-'),
                TextEntry::make('location_address')
                    ->placeholder('-'),
                IconEntry::make('pickup_enabled')
                    ->boolean(),
                IconEntry::make('shipping_enabled')
                    ->boolean(),
                TextEntry::make('shipping_price')
                    ->money('EUR'),
                TextEntry::make('views_count')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Listing $record): bool => $record->trashed()),
            ]);
    }
}
