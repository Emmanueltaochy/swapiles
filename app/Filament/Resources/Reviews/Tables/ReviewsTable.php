<?php

namespace App\Filament\Resources\Reviews\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reviewer.name')
                    ->label('Auteur')
                    ->default('—')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('reviewed.name')
                    ->label('Concerne')
                    ->default('—')
                    ->searchable(),
                TextColumn::make('rating')
                    ->label('Note')
                    ->badge()
                    ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state) . str_repeat('☆', max(0, 5 - (int) $state)))
                    ->color(fn ($state) => (int) $state >= 4 ? 'success' : ((int) $state >= 3 ? 'warning' : 'danger'))
                    ->sortable(),
                TextColumn::make('comment')
                    ->label('Avis')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('rating')
                    ->label('Note')
                    ->options([
                        5 => '★★★★★',
                        4 => '★★★★',
                        3 => '★★★',
                        2 => '★★',
                        1 => '★',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
