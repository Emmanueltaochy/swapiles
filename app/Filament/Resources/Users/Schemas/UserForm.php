<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('sharetribe_id'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('avatar'),
                TextInput::make('stripe_account_id'),
                TextInput::make('territoire')
                    ->default('la-reunion'),
                TextInput::make('comment_connu'),
                Toggle::make('is_pro')
                    ->required(),
                Toggle::make('is_banned')
                    ->required(),
                TextInput::make('rating')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('transactions_count')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
