<?php

namespace App\Filament\Resources\AnalyticsEvents;

use App\Filament\Resources\AnalyticsEvents\Pages\ListAnalyticsEvents;
use App\Filament\Resources\AnalyticsEvents\Tables\AnalyticsEventsTable;
use App\Models\AnalyticsEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnalyticsEventResource extends Resource
{
    protected static ?string $model = AnalyticsEvent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cursor-arrow-ripple';

    protected static ?int $navigationSort = 45;

    public static function table(Table $table): Table
    {
        return AnalyticsEventsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function getNavigationLabel(): string
    {
        return 'Visites du site';
    }

    public static function getModelLabel(): string
    {
        return 'visite';
    }

    public static function getPluralModelLabel(): string
    {
        return 'visites';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Communauté';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnalyticsEvents::route('/'),
        ];
    }
}
