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
                TextInput::make('location_address')
                    ->label('Adresse exacte (privée)'),
                TextInput::make('pickup_city')
                    ->label('Ville (remise main propre)'),
                TextInput::make('pickup_postal_code')
                    ->label('Code postal (remise main propre)'),
                TextInput::make('weight_kg')
                    ->label('Poids du colis (en grammes)')
                    ->helperText('En GRAMMES (ex. 250 pour 250 g). Sert au calcul des frais Colissimo.')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(30000)
                    ->step(10)
                    ->suffix('g')
                    // La colonne est stockée en kg : on convertit à l'affichage et à l'enregistrement.
                    ->formatStateUsing(fn ($state) => $state !== null ? (int) round(((float) $state) * 1000) : null)
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? round(((float) $state) / 1000, 3) : null),
                Toggle::make('pickup_enabled')
                    ->label('Remise en main propre'),
                Toggle::make('allows_hand_delivery')
                    ->label('Autorise la main propre'),
                Toggle::make('allows_colissimo')
                    ->label('Livraison Colissimo'),
                Toggle::make('requires_online_payment')
                    ->label('Paiement CB en ligne'),
                Toggle::make('allows_offers')
                    ->label('Accepte les offres'),
                Toggle::make('allows_exchange')
                    ->label('Accepte l’échange'),
                Toggle::make('shipping_enabled'),
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
