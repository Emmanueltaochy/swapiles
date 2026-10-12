<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Transaction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionInfolist
{
    private const MODES_PAIEMENT = [
        'especes' => '💵 Espèces (déclaré par le vendeur)',
        'echange' => '🔄 Échange (déclaré par le vendeur)',
        'don' => '🎁 Don (déclaré par le vendeur)',
        'cb' => '💳 Carte bancaire',
        'card' => '💳 Carte bancaire',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('La vente')
                    ->icon('heroicon-o-shopping-bag')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('fiabilite')
                            ->label('Fiabilité')
                            ->state(fn (Transaction $record) => $record->fiabiliteLibelle())
                            ->badge()
                            ->color(fn (Transaction $record) => Transaction::FIABILITES[$record->fiabilite()][1])
                            ->helperText(fn (Transaction $record) => Transaction::FIABILITES[$record->fiabilite()][2])
                            ->columnSpanFull(),
                        TextEntry::make('listing.title')
                            ->label('Annonce')
                            ->weight('bold')
                            ->placeholder('Annonce supprimée')
                            ->url(fn (Transaction $record) => $record->listing ? ListingResource::getUrl('view', ['record' => $record->listing]) : null),
                        TextEntry::make('seller.name')
                            ->label('Vendeur')
                            ->placeholder('—')
                            ->icon('heroicon-o-user')
                            ->url(fn (Transaction $record) => $record->seller ? UserResource::getUrl('view', ['record' => $record->seller]) : null),
                        TextEntry::make('acheteur')
                            ->label('Acheteur')
                            ->icon('heroicon-o-user')
                            ->state(fn (Transaction $record) => self::acheteur($record))
                            ->color(fn (Transaction $record) => $record->buyer ? null : 'gray')
                            ->url(fn (Transaction $record) => $record->buyer ? UserResource::getUrl('view', ['record' => $record->buyer]) : null),
                        TextEntry::make('amount')
                            ->label('Prix')
                            ->money('EUR'),
                        TextEntry::make('payment_method')
                            ->label('Paiement')
                            ->formatStateUsing(fn (?string $state) => self::MODES_PAIEMENT[$state] ?? ($state ?: '—')),
                        TextEntry::make('created_at')
                            ->label('Date')
                            ->dateTime('d/m/Y à H:i'),
                        TextEntry::make('acheteur_confirme_at')
                            ->label('Confirmée par l\'acheteur le')
                            ->dateTime('d/m/Y à H:i')
                            ->visible(fn (Transaction $record) => $record->acheteur_confirme_at !== null),
                        TextEntry::make('acheteur_conteste_at')
                            ->label('Contestée par l\'acheteur le')
                            ->dateTime('d/m/Y à H:i')
                            ->color('danger')
                            ->visible(fn (Transaction $record) => $record->acheteur_conteste_at !== null),
                    ]),

                Section::make('Qui était en contact avec le vendeur pour cette annonce ?')
                    ->description('L\'acheteur n\'est pas connu : voici les membres qui ont écrit au vendeur ou mis l\'annonce en favori. Si personne, c\'est probablement une annonce retirée ou vendue ailleurs.')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->columnSpanFull()
                    ->visible(fn (Transaction $record) => $record->estDeclaree() && ! $record->buyer_id && $record->listing)
                    ->schema([
                        ViewEntry::make('contacts')
                            ->hiddenLabel()
                            ->view('filament.infolists.transaction-contacts'),
                    ]),

                Section::make('Cheminement du produit')
                    ->description('Achat → paiement → expédition / remise → réception → versement')
                    ->schema([
                        ViewEntry::make('cheminement')
                            ->hiddenLabel()
                            ->view('filament.infolists.transaction-timeline'),
                    ])
                    ->columnSpanFull(),

                Section::make('Argent')
                    ->icon('heroicon-o-banknotes')
                    ->columns(4)
                    ->columnSpanFull()
                    ->collapsed(fn (Transaction $record) => $record->estDeclaree())
                    ->schema([
                        TextEntry::make('buyer_protection_fee')->label('Protection acheteur')->money('EUR'),
                        TextEntry::make('commission')->label('Commission')->money('EUR'),
                        TextEntry::make('shipping_fee')->label('Livraison')->money('EUR'),
                        TextEntry::make('seller_amount')->label('Pour le vendeur')->money('EUR'),
                        TextEntry::make('status')->label('Statut')->badge(),
                        TextEntry::make('stripe_payment_intent_id')->label('Paiement Stripe')->placeholder('—')->copyable(),
                        TextEntry::make('completed_at')->label('Terminée le')->dateTime('d/m/Y à H:i')->placeholder('—'),
                        TextEntry::make('id')->label('N° de vente'),
                    ]),
            ]);
    }

    private static function acheteur(Transaction $record): string
    {
        if ($record->buyer) {
            return $record->buyer->name;
        }

        if ($record->buyer_full_name) {
            return $record->buyer_full_name;
        }

        return match ($record->payment_method) {
            'especes' => 'Inconnu — le vendeur a indiqué « hors Swap\'Îles »',
            'echange', 'don' => 'Non indiqué par le vendeur',
            default => 'Compte supprimé',
        };
    }
}
