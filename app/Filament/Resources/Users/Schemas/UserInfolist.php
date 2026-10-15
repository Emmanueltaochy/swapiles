<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use App\Support\HistoriqueMembre;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->icon('heroicon-o-user-circle')
                    ->columns(3)
                    ->schema([
                        ImageEntry::make('avatar')
                            ->label('Photo')
                            ->circular()
                            ->defaultImageUrl(fn (User $r) => 'https://ui-avatars.com/api/?name=' . urlencode($r->name ?? 'S') . '&background=0d9488&color=fff'),
                        TextEntry::make('name')
                            ->label('Nom')
                            ->weight('bold')
                            ->size('lg')
                            ->copyable(),
                        TextEntry::make('email')
                            ->label('E-mail')
                            ->icon('heroicon-o-envelope')
                            ->copyable()
                            ->badge()
                            ->color(fn (User $r) => $r->email_verified_at ? 'success' : 'warning')
                            ->formatStateUsing(fn ($state, User $r) => $state . ($r->email_verified_at ? ' ✓ vérifié' : ' • non vérifié')),
                        TextEntry::make('phone')
                            ->label('Téléphone')
                            ->icon('heroicon-o-phone')
                            ->placeholder('Non renseigné')
                            ->copyable(),
                        TextEntry::make('territoire')
                            ->label('Île')
                            ->badge()
                            ->color('info')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Inscrit le')
                            ->dateTime('d/m/Y à H:i')
                            ->since()
                            ->placeholder('—'),
                    ]),

                Section::make('Activité sur la plateforme')
                    ->icon('heroicon-o-chart-bar')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('rating')
                            ->label('Note vendeur')
                            ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 1, ',', ' ') . ' / 5 ⭐' : 'Aucune note')
                            ->badge()
                            ->color('warning'),
                        TextEntry::make('transactions_count')
                            ->label('Ventes réalisées')
                            ->numeric()
                            ->badge()
                            ->color('success'),
                        TextEntry::make('listings_total')
                            ->label('Annonces publiées')
                            ->state(fn (User $r) => method_exists($r, 'listings') ? $r->listings()->where('status', 'published')->count() : 0)
                            ->badge()
                            ->color('info'),
                        TextEntry::make('listings_sold')
                            ->label('Annonces vendues')
                            ->state(fn (User $r) => method_exists($r, 'listings') ? $r->listings()->where('status', 'sold')->count() : 0)
                            ->badge()
                            ->color('gray'),
                    ]),

                Section::make('Habitudes')
                    ->description('Sur les ' . HistoriqueMembre::JOURS . ' derniers jours.')
                    ->icon('heroicon-o-sparkles')
                    ->columnSpanFull()
                    ->schema([
                        View::make('filament.membres.habitudes')
                            ->viewData(fn (User $record) => ['habitudes' => HistoriqueMembre::habitudes($record)]),
                    ]),

                Section::make('Historique d\'activité')
                    ->description('Connexions, annonces, favoris, annonces regardées, recherches, messages, achats, ventes, avis.')
                    ->icon('heroicon-o-clock')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        View::make('filament.membres.chronologie')
                            ->viewData(fn (User $record) => ['evenements' => HistoriqueMembre::chronologie($record)]),
                    ]),

                Section::make('Paiements (Stripe Connect)')
                    ->icon('heroicon-o-credit-card')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('stripe_state')
                            ->label('État du compte')
                            ->state(fn (User $r) => (
                                $r->stripe_account_id
                                && $r->stripe_charges_enabled
                                && $r->stripe_payouts_enabled
                                && $r->stripe_details_submitted
                            ) ? 'Configuré — peut recevoir des paiements' : 'Non configuré')
                            ->badge()
                            ->color(fn ($state) => str_starts_with((string) $state, 'Configuré') ? 'success' : 'gray'),
                        TextEntry::make('stripe_account_id')
                            ->label('ID compte Stripe')
                            ->placeholder('Aucun')
                            ->copyable(),
                        IconEntry::make('stripe_charges_enabled')->label('Encaissements')->boolean(),
                        IconEntry::make('stripe_payouts_enabled')->label('Virements')->boolean(),
                    ]),

                Section::make('Adresse')
                    ->icon('heroicon-o-map-pin')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('address_line1')->label('Adresse')->placeholder('—'),
                        TextEntry::make('address_line2')->label('Complément')->placeholder('—'),
                        TextEntry::make('postal_code')->label('Code postal')->placeholder('—'),
                        TextEntry::make('city')->label('Ville')->placeholder('—'),
                    ]),

                Section::make('Statut & informations')
                    ->icon('heroicon-o-shield-check')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('is_pro')
                            ->label('Type de compte')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Pro' : 'Particulier')
                            ->color(fn ($state) => $state ? 'info' : 'gray'),
                        TextEntry::make('is_banned')
                            ->label('Accès')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? '⛔ Banni' : 'Actif')
                            ->color(fn ($state) => $state ? 'danger' : 'success'),
                        TextEntry::make('comment_connu')
                            ->label('Comment nous a connu')
                            ->placeholder('Non renseigné')
                            ->badge()
                            ->color('gray'),
                    ]),
            ]);
    }
}
