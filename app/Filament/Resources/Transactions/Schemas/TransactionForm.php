<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Correction manuelle d'une vente. Les parties (annonce, vendeur, acheteur),
 * les montants et la référence Stripe sont en lecture seule : les changer à
 * la main fausserait les paiements, les versements et le parrainage.
 */
class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('listing_id')
                    ->label('Annonce')
                    ->relationship('listing', 'title')
                    ->disabled(),
                TextInput::make('amount')
                    ->label('Montant payé (€)')
                    ->numeric()
                    ->disabled(),
                Select::make('seller_id')
                    ->label('Vendeur')
                    ->relationship('seller', 'name')
                    ->disabled(),
                Select::make('buyer_id')
                    ->label('Acheteur')
                    ->relationship('buyer', 'name')
                    ->disabled(),
                Select::make('payment_method')
                    ->label('Mode de paiement')
                    ->options(['cb' => 'Carte bancaire', 'especes' => 'Espèces', 'echange' => 'Échange', 'don' => 'Don'])
                    ->required(),
                Select::make('status')
                    ->label('Statut')
                    ->options([
                        'inquiry' => 'Demande',
                        'pending' => 'En attente',
                        'paid' => 'Payée',
                        'completed' => 'Terminée',
                        'cancelled' => 'Annulée',
                        'refunded' => 'Remboursée',
                    ])
                    ->required(),
                TextInput::make('stripe_payment_intent_id')
                    ->label('Référence du paiement Stripe')
                    ->disabled(),
                DateTimePicker::make('completed_at')
                    ->label('Terminée le'),
            ]);
    }
}
