<?php

namespace App\Filament\Resources\Echanges;

use App\Filament\Resources\Echanges\Pages\ListEchanges;
use App\Filament\Resources\Echanges\Tables\EchangesTable;
use App\Filament\Resources\Echanges\Widgets\BilanEchanges;
use App\Models\ExchangeProposal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Qui propose quoi en échange de quel article. */
class EchangeResource extends Resource
{
    use \App\Filament\Concerns\TitresEnFrancais;

    protected static ?string $model = ExchangeProposal::class;

    protected static ?string $slug = 'echanges';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return EchangesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['proposer', 'seller', 'listing.images', 'offeredListing.images']);
    }

    public static function getNavigationLabel(): string
    {
        return 'Échanges';
    }

    public static function getModelLabel(): string
    {
        return 'proposition d’échange';
    }

    public static function getPluralModelLabel(): string
    {
        return 'échanges';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Ventes';
    }

    public static function getNavigationBadge(): ?string
    {
        $n = ExchangeProposal::where('status', 'pending')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Propositions en attente de réponse du vendeur';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getWidgets(): array
    {
        return [BilanEchanges::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEchanges::route('/'),
        ];
    }
}
