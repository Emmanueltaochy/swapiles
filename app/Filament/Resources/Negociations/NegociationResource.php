<?php

namespace App\Filament\Resources\Negociations;

use App\Filament\Resources\Negociations\Pages\ListNegociations;
use App\Filament\Resources\Negociations\Tables\NegociationsTable;
use App\Filament\Resources\Negociations\Widgets\BilanNegociations;
use App\Models\ListingOffer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Qui propose combien à qui, pour quel article : le suivi des deals. */
class NegociationResource extends Resource
{
    use \App\Filament\Concerns\TitresEnFrancais;

    protected static ?string $model = ListingOffer::class;

    protected static ?string $slug = 'negociations';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return NegociationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['buyer', 'seller', 'listing.images']);
    }

    public static function getNavigationLabel(): string
    {
        return 'Négociations';
    }

    public static function getModelLabel(): string
    {
        return 'offre';
    }

    public static function getPluralModelLabel(): string
    {
        return 'négociations';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Ventes';
    }

    /** Pastille : offres qui attendent la réponse du vendeur. */
    public static function getNavigationBadge(): ?string
    {
        $n = ListingOffer::where('status', 'pending')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Offres en attente de réponse du vendeur';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getWidgets(): array
    {
        return [BilanNegociations::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNegociations::route('/'),
        ];
    }
}
