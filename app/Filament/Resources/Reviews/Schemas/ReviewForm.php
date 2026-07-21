<?php

namespace App\Filament\Resources\Reviews\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sharetribe_id'),
                TextInput::make('transaction_id')
                    ->required()
                    ->numeric(),
                TextInput::make('reviewer_id')
                    ->required()
                    ->numeric(),
                TextInput::make('reviewed_id')
                    ->required()
                    ->numeric(),
                TextInput::make('rating')
                    ->required()
                    ->numeric(),
                Textarea::make('comment')
                    ->columnSpanFull(),
            ]);
    }
}
