<?php

namespace App\Filament\Resources\Listings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ListingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image')
                    ->label('Photo')
                    ->getStateUsing(fn ($record) => optional($record->images()->orderBy('order')->first())->url)
                    ->circular()
                    ->size(50),
                TextColumn::make('sharetribe_id')
                    ->searchable(),
                TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('price')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('listing_type')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('territoire')
                    ->searchable(),
                TextColumn::make('category_level1')
                    ->searchable(),
                TextColumn::make('category_level2')
                    ->searchable(),
                TextColumn::make('category_level3')
                    ->searchable(),
                TextColumn::make('etat')
                    ->searchable(),
                TextColumn::make('marque')
                    ->searchable(),
                TextColumn::make('taille')
                    ->searchable(),
                TextColumn::make('location_address')
                    ->searchable(),
                IconColumn::make('pickup_enabled')
                    ->boolean(),
                IconColumn::make('shipping_enabled')
                    ->boolean(),
                TextColumn::make('shipping_price')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('views_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
