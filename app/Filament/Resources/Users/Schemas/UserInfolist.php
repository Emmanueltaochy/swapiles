<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('email_verified_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('sharetribe_id')
                    ->placeholder('-'),
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('avatar')
                    ->placeholder('-'),
                TextEntry::make('stripe_account_id')
                    ->placeholder('-'),
                TextEntry::make('territoire')
                    ->placeholder('-'),
                TextEntry::make('comment_connu')
                    ->placeholder('-'),
                IconEntry::make('is_pro')
                    ->boolean(),
                IconEntry::make('is_banned')
                    ->boolean(),
                TextEntry::make('rating')
                    ->numeric(),
                TextEntry::make('transactions_count')
                    ->numeric(),
            ]);
    }
}
