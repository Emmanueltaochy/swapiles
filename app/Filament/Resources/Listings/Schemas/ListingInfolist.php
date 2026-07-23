<?php

namespace App\Filament\Resources\Listings\Schemas;

use App\Filament\Resources\Users\UserResource;
use App\Models\Listing;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ListingInfolist
{
    protected const TYPE_LABELS = [
        'achat' => 'Vente',
        'negoce-prix' => 'Négociable',
        'echange-produits' => 'Échange',
        'don' => 'Don',
        'location-vetements' => 'Location',
    ];

    protected const STATUS_LABELS = [
        'published' => 'En ligne',
        'draft' => 'Masquée',
        'sold' => 'Vendue',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Aperçu')
                    ->icon('heroicon-o-photo')
                    ->columns(3)
                    ->schema([
                        ImageEntry::make('images')
                            ->label('Photos')
                            ->state(fn (Listing $r) => $r->images->map(fn ($i) => $i->url)->all())
                            ->stacked()
                            ->circular()
                            ->limit(6)
                            ->limitedRemainingText()
                            ->columnSpanFull(),
                        TextEntry::make('title')
                            ->label('Titre')
                            ->weight('bold')
                            ->size('lg')
                            ->columnSpan(2),
                        TextEntry::make('price')
                            ->label('Prix')
                            ->formatStateUsing(fn ($state) => (float) $state > 0 ? number_format((float) $state, 2, ',', ' ') . ' €' : 'Gratuit')
                            ->weight('bold')
                            ->size('lg')
                            ->color('success'),
                        TextEntry::make('listing_type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn (?string $s) => self::TYPE_LABELS[$s] ?? $s)
                            ->color('info'),
                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->formatStateUsing(fn (?string $s) => self::STATUS_LABELS[$s] ?? $s)
                            ->color(fn (?string $s) => match ($s) {
                                'published' => 'success',
                                'sold' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('territoire')
                            ->label('Île')
                            ->badge()
                            ->color('info'),
                    ]),

                Section::make('Vendeur')
                    ->icon('heroicon-o-user')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Nom du vendeur')
                            ->weight('bold')
                            ->color('primary')
                            ->url(fn (Listing $r) => $r->user_id ? UserResource::getUrl('view', ['record' => $r->user_id]) : null)
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->placeholder('—'),
                        TextEntry::make('user.email')
                            ->label('E-mail')
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('user.phone')
                            ->label('Téléphone')
                            ->copyable()
                            ->placeholder('—'),
                    ]),

                Section::make('Détails de l\'article')
                    ->icon('heroicon-o-tag')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('category_path')
                            ->label('Catégorie')
                            ->state(fn (Listing $r) => collect([$r->category_level1, $r->category_level2, $r->category_level3])->filter()->implode(' › ') ?: '—')
                            ->badge()
                            ->color('gray')
                            ->columnSpanFull(),
                        TextEntry::make('etat')->label('État')->placeholder('—')->badge(),
                        TextEntry::make('marque')->label('Marque')->placeholder('—'),
                        TextEntry::make('taille')->label('Taille')->placeholder('—'),
                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('Aucune description')
                            ->columnSpanFull()
                            ->prose(),
                    ]),

                Section::make('Livraison & paiement')
                    ->icon('heroicon-o-truck')
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        IconEntry::make('requires_online_payment')->label('Paiement en ligne')->boolean(),
                        IconEntry::make('allows_colissimo')->label('Colissimo')->boolean(),
                        IconEntry::make('allows_hand_delivery')->label('Main propre')->boolean(),
                        TextEntry::make('shipping_price')
                            ->label('Frais de port')
                            ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2, ',', ' ') . ' €' : '—'),
                        TextEntry::make('weight_kg')->label('Poids (kg)')->placeholder('—'),
                        TextEntry::make('location_address')->label('Localisation')->placeholder('—'),
                    ]),

                Section::make('Statistiques')
                    ->icon('heroicon-o-chart-bar')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('views_count')
                            ->label('Vues (hors robots)')
                            ->numeric()
                            ->badge()
                            ->color('info'),
                        TextEntry::make('favorites_total')
                            ->label('Favoris')
                            ->state(fn (Listing $r) => method_exists($r, 'favoritedBy') ? $r->favoritedBy()->count() : 0)
                            ->badge()
                            ->color('danger'),
                        TextEntry::make('created_at')
                            ->label('Publiée le')
                            ->dateTime('d/m/Y H:i')
                            ->since(),
                        TextEntry::make('updated_at')
                            ->label('Modifiée le')
                            ->dateTime('d/m/Y H:i')
                            ->since(),
                    ]),
            ]);
    }
}
