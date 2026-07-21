<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('sharetribe_id')
                    ->placeholder('-'),
                TextEntry::make('listing_id')
                    ->numeric(),
                TextEntry::make('seller_id')
                    ->numeric(),
                TextEntry::make('buyer_id')
                    ->numeric(),
                TextEntry::make('amount')
                    ->numeric(),
                TextEntry::make('buyer_protection_fee')
                    ->label('Protection acheteur')
                    ->money('EUR'),
                TextEntry::make('commission')
                    ->numeric(),
                TextEntry::make('currency'),
                TextEntry::make('payment_method')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('stripe_payment_intent_id')
                    ->placeholder('-'),
                TextEntry::make('completed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
