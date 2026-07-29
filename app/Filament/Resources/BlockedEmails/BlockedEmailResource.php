<?php

namespace App\Filament\Resources\BlockedEmails;

use App\Filament\Resources\BlockedEmails\Pages\CreateBlockedEmail;
use App\Filament\Resources\BlockedEmails\Pages\EditBlockedEmail;
use App\Filament\Resources\BlockedEmails\Pages\ListBlockedEmails;
use App\Models\BlockedEmail;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlockedEmailResource extends Resource
{
    protected static ?string $model = BlockedEmail::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')
                ->label('E-mail à bloquer')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(191),
            TextInput::make('reason')
                ->label('Motif (facultatif)')
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')
                    ->label('E-mail bloqué')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),
                TextColumn::make('reason')
                    ->label('Motif')
                    ->default('—')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Bloqué le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->label('Débloquer')
                    ->modalHeading('Débloquer cet e-mail ?')
                    ->modalDescription('Cette adresse pourra de nouveau servir à s’inscrire.'),
            ]);
    }

    public static function getNavigationLabel(): string
    {
        return 'E-mails bloqués';
    }

    public static function getModelLabel(): string
    {
        return 'e-mail bloqué';
    }

    public static function getPluralModelLabel(): string
    {
        return 'e-mails bloqués';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Communauté';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = BlockedEmail::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlockedEmails::route('/'),
            'create' => CreateBlockedEmail::route('/create'),
            'edit' => EditBlockedEmail::route('/{record}/edit'),
        ];
    }
}
