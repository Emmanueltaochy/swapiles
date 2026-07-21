<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sharetribe_id'),
                TextInput::make('listing_id')
                    ->required()
                    ->numeric(),
                TextInput::make('seller_id')
                    ->required()
                    ->numeric(),
                TextInput::make('buyer_id')
                    ->required()
                    ->numeric(),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('commission')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('currency')
                    ->required()
                    ->default('EUR'),
                Select::make('payment_method')
                    ->options(['cb' => 'Cb', 'especes' => 'Especes', 'echange' => 'Echange', 'don' => 'Don'])
                    ->default('especes')
                    ->required(),
                Select::make('status')
                    ->options([
            'inquiry' => 'Inquiry',
            'pending' => 'Pending',
            'paid' => 'Paid',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ])
                    ->default('inquiry')
                    ->required(),
                TextInput::make('stripe_payment_intent_id'),
                DateTimePicker::make('completed_at'),
            ]);
    }
}
