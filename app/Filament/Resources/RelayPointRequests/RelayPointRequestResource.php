<?php

namespace App\Filament\Resources\RelayPointRequests;

use App\Filament\Resources\RelayPointRequests\Pages\ListRelayPointRequests;
use App\Models\RelayPointRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Resources\Resource;

/** Demandes de membres : « un point relais près de chez moi ». */
class RelayPointRequestResource extends Resource
{
    protected static ?string $model = RelayPointRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Demandes de points relais';

    protected static ?string $modelLabel = 'demande de point relais';

    protected static ?string $pluralModelLabel = 'demandes de points relais';

    protected static string | \UnitEnum | null $navigationGroup = 'Points relais';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->groups([
                Group::make('city')->label('Ville'),
                Group::make('territoire')->label('Île'),
            ])
            ->columns([
                TextColumn::make('created_at')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('city')->label('Ville / quartier')->weight('bold')->searchable()
                    ->description(fn (RelayPointRequest $r) => $r->territoire),
                TextColumn::make('business_name')->label('Commerce proposé')->placeholder('—')->searchable()
                    ->description(fn (RelayPointRequest $r) => $r->business_address),
                TextColumn::make('already_relay')->label('Déjà point relais ?')->badge()
                    ->formatStateUsing(fn (?string $s) => RelayPointRequest::DEJA_RELAIS[$s] ?? $s)
                    ->color(fn (?string $s) => $s === 'oui' ? 'success' : 'gray')
                    ->description(fn (RelayPointRequest $r) => $r->reseauxLisibles() ?: null),
                TextColumn::make('name')->label('Demandé par')->searchable()
                    ->description(fn (RelayPointRequest $r) => $r->email . ($r->phone ? ' · ' . $r->phone : '')),
                TextColumn::make('message')->label('Message')->limit(50)->placeholder('—')->toggleable(),
                TextColumn::make('status')->label('Suivi')->badge()
                    ->formatStateUsing(fn (?string $s) => RelayPointRequest::STATUTS[$s] ?? $s)
                    ->color(fn (?string $s) => match ($s) {
                        'nouvelle' => 'warning', 'contactee' => 'info', 'ouverte' => 'success', default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Suivi')->options(RelayPointRequest::STATUTS),
                SelectFilter::make('territoire')->label('Île')->options(array_combine(\App\Support\TerritoireContext::LABELS, \App\Support\TerritoireContext::LABELS)),
            ])
            ->recordActions([
                Action::make('suivi')
                    ->label('Suivi')
                    ->icon('heroicon-o-arrow-path')
                    ->schema([
                        Select::make('status')->label('Où en est cette demande ?')->options(RelayPointRequest::STATUTS)->required(),
                    ])
                    ->fillForm(fn (RelayPointRequest $record) => ['status' => $record->status])
                    ->action(fn (RelayPointRequest $record, array $data) => $record->update(['status' => $data['status']])),
                Action::make('ecrire')
                    ->label('Écrire')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->url(fn (RelayPointRequest $record) => 'mailto:' . $record->email . '?subject=' . rawurlencode('Votre demande de point relais Swap\'Îles')),
                DeleteAction::make(),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = RelayPointRequest::where('status', 'nouvelle')->count();

        return $n > 0 ? (string) $n : null;
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
            'index' => ListRelayPointRequests::route('/'),
        ];
    }
}
