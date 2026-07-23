<?php

namespace App\Filament\Resources\Messages;

use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Tables\MessagesTable;
use App\Models\Message;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return MessagesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['sender', 'receiver', 'listing']);
    }

    public static function getNavigationLabel(): string
    {
        return 'Messages';
    }

    public static function getModelLabel(): string
    {
        return 'message';
    }

    public static function getPluralModelLabel(): string
    {
        return 'messages';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Communauté';
    }

    /** Pastille : nombre de messages non lus (modération). */
    public static function getNavigationBadge(): ?string
    {
        $count = Message::whereNull('read_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessages::route('/'),
        ];
    }
}
