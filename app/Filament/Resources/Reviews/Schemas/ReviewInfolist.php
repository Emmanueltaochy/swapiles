<?php

namespace App\Filament\Resources\Reviews\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('sharetribe_id')
                    ->placeholder('-'),
                TextEntry::make('transaction_id')
                    ->numeric(),
                TextEntry::make('reviewer_id')
                    ->numeric(),
                TextEntry::make('reviewed_id')
                    ->numeric(),
                TextEntry::make('rating')
                    ->numeric(),
                TextEntry::make('comment')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
