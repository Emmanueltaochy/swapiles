<?php

namespace App\Filament\Resources\Listings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ListingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sharetribe_id'),
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('€'),
                TextInput::make('currency')
                    ->required()
                    ->default('EUR'),
                Select::make('listing_type')
                    ->options([
            'achat' => 'Achat',
            'echange-produits' => 'Echange produits',
            'don' => 'Don',
            'location-vetements' => 'Location vetements',
            'negoce-prix' => 'Negoce prix',
        ])
                    ->default('achat')
                    ->required(),
                Select::make('status')
                    ->options(['draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed', 'sold' => 'Sold'])
                    ->default('draft')
                    ->required(),
                TextInput::make('territoire')
                    ->required()
                    ->default('la-reunion'),
                TextInput::make('category_level1'),
                TextInput::make('category_level2'),
                TextInput::make('category_level3'),
                TextInput::make('etat'),
                TextInput::make('marque'),
                TextInput::make('taille'),
                TextInput::make('couleurs'),
                TextInput::make('location_address'),
                Toggle::make('pickup_enabled')
                    ->required(),
                Toggle::make('shipping_enabled')
                    ->required(),
                TextInput::make('shipping_price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('€'),
                TextInput::make('views_count')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
