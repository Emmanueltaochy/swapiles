<?php

namespace App\Filament\Resources\RelayPoints;

use App\Filament\Resources\RelayPoints\Pages\CreateRelayPoint;
use App\Filament\Resources\RelayPoints\Pages\EditRelayPoint;
use App\Filament\Resources\RelayPoints\Pages\ListRelayPoints;
use App\Models\RelayPoint;
use App\Support\DomTomGeo;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Points relais partenaires (commerçants). Pilote La Réunion : l'admin crée un
 * point relais, il apparaît alors comme option de retrait au paiement CB pour
 * les annonces du même territoire.
 */
class RelayPointResource extends Resource
{
    protected static ?string $model = RelayPoint::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nom du commerçant / point relais')
                ->required()
                ->maxLength(191),
            Select::make('territoire')
                ->label('Territoire')
                ->options(array_combine(DomTomGeo::territoires(), DomTomGeo::territoires()))
                ->default('La Réunion')
                ->required()
                ->native(false),
            TextInput::make('address')
                ->label('Adresse')
                ->maxLength(255),
            TextInput::make('postal_code')
                ->label('Code postal')
                ->maxLength(20),
            TextInput::make('city')
                ->label('Ville')
                ->maxLength(120),
            TextInput::make('contact_name')
                ->label('Contact (facultatif)')
                ->maxLength(120),
            TextInput::make('contact_phone')
                ->label('Téléphone (facultatif)')
                ->tel()
                ->maxLength(40),
            TextInput::make('opening_hours')
                ->label('Horaires (facultatif)')
                ->placeholder('Lun–Sam 9h–18h')
                ->maxLength(191),
            Textarea::make('notes')
                ->label('Notes internes (facultatif)')
                ->rows(2)
                ->columnSpanFull(),
            Toggle::make('is_active')
                ->label('Actif (proposé au paiement)')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Point relais')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('territoire')
                    ->label('Territoire')
                    ->badge()
                    ->sortable(),
                TextColumn::make('city')
                    ->label('Ville')
                    ->default('—')
                    ->searchable(),
                TextColumn::make('contact_phone')
                    ->label('Téléphone')
                    ->default('—'),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                TextColumn::make('transactions_count')
                    ->label('Colis')
                    ->counts('transactions')
                    ->badge()
                    ->color('info'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('territoire')
                    ->label('Territoire')
                    ->options(array_combine(DomTomGeo::territoires(), DomTomGeo::territoires())),
                TernaryFilter::make('is_active')
                    ->label('Actif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getNavigationLabel(): string
    {
        return 'Points relais';
    }

    public static function getModelLabel(): string
    {
        return 'point relais';
    }

    public static function getPluralModelLabel(): string
    {
        return 'points relais';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketplace';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRelayPoints::route('/'),
            'create' => CreateRelayPoint::route('/create'),
            'edit' => EditRelayPoint::route('/{record}/edit'),
        ];
    }
}
