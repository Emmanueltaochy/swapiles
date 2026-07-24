<?php

namespace App\Filament\Resources\Favorites;

use App\Filament\Resources\Favorites\Pages\ListFavorites;
use App\Filament\Resources\Favorites\Tables\FavoritesTable;
use App\Models\Favorite;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FavoriteResource extends Resource
{
    protected static ?string $model = Favorite::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?int $navigationSort = 25;

    public static function table(Table $table): Table
    {
        return FavoritesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'listing.user', 'listing.images']);
    }

    public static function getNavigationLabel(): string
    {
        return 'Favoris';
    }

    public static function getModelLabel(): string
    {
        return 'favori';
    }

    public static function getPluralModelLabel(): string
    {
        return 'favoris';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Communauté';
    }

    /** Pastille : nombre total de mises en favori. */
    public static function getNavigationBadge(): ?string
    {
        $count = Favorite::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFavorites::route('/'),
        ];
    }
}
