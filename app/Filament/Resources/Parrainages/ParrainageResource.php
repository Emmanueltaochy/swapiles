<?php

namespace App\Filament\Resources\Parrainages;

use App\Filament\Resources\Parrainages\Pages\ListParrainages;
use App\Models\Parrainage;
use App\Support\ParrainageService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Parrainages : qui a parrainé qui, où en est chaque filleul, récompenses et refus. */
class ParrainageResource extends Resource
{
    use \App\Filament\Concerns\TitresEnFrancais;

    protected static ?string $model = Parrainage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Parrainages';

    protected static ?string $modelLabel = 'parrainage';

    protected static ?string $pluralModelLabel = 'parrainages';

    protected static string | \UnitEnum | null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        $euros = fn ($c) => number_format(((int) $c) / 100, 2, ',', ' ') . ' €';

        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['parrain:id,name,email,ambassadeur_at,a_mettre_en_avant_at', 'filleul:id,name,email']))
            ->columns([
                TextColumn::make('created_at')->label('Inscription')->date('d/m/Y')->sortable(),
                TextColumn::make('parrain.name')->label('Parrain')->searchable()
                    ->description(fn (Parrainage $r) => trim(($r->parrain?->a_mettre_en_avant_at ? '⭐ à mettre en avant · ' : '') . ($r->parrain?->ambassadeur_at ? '💚 Ambassadeur · ' : '') . $r->code))
                    ->url(fn (Parrainage $r) => $r->parrain_id ? \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $r->parrain_id]) : null),
                TextColumn::make('filleul.name')->label('Filleul')->searchable()
                    ->description(fn (Parrainage $r) => $r->filleul?->email)
                    ->url(fn (Parrainage $r) => $r->filleul_id ? \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $r->filleul_id]) : null),
                TextColumn::make('source')->label('Via')
                    ->formatStateUsing(fn (?string $state) => ['lien' => 'Lien', 'saisie' => 'Code saisi', 'appariement' => 'Lien (appli installée)'][$state] ?? $state),
                TextColumn::make('statut')->label('Statut')->badge()
                    ->formatStateUsing(fn (?string $state) => Parrainage::STATUTS[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'valide' => 'success', 'achat_en_cours' => 'warning', 'refuse', 'annule' => 'danger', default => 'gray',
                    })
                    ->description(fn (Parrainage $r) => $r->motif_refus ? (Parrainage::MOTIFS[$r->motif_refus] ?? $r->motif_refus) : null),
                TextColumn::make('montant_centimes')->label('Récompense')->formatStateUsing(fn ($state) => $euros($state))->sortable(),
                TextColumn::make('transaction_id')->label('Commande')->placeholder('—')
                    ->formatStateUsing(fn ($state) => '#' . $state)
                    ->url(fn (Parrainage $r) => $r->transaction_id ? \App\Filament\Resources\Transactions\TransactionResource::getUrl('view', ['record' => $r->transaction_id]) : null),
                TextColumn::make('valide_at')->label('Validé le')->date('d/m/Y')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(Parrainage::STATUTS),
                SelectFilter::make('motif_refus')->label('Motif de refus')->options(Parrainage::MOTIFS),
            ])
            ->recordActions([
                Action::make('annuler')
                    ->label('Annuler la récompense')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Parrainage $r) => $r->statut === 'valide')
                    ->requiresConfirmation()
                    ->modalDescription('Le crédit encore disponible est retiré du portefeuille du parrain. Ce qu’il a déjà dépensé ne peut pas être récupéré automatiquement.')
                    ->schema([TextInput::make('raison')->label('Raison (fraude constatée, faux compte…)')->required()->maxLength(255)])
                    ->action(function (Parrainage $record, array $data) {
                        $bilan = ParrainageService::annulerRecompense($record, $data['raison']);
                        FilamentNotification::make()
                            ->title('Récompense annulée')
                            ->body(number_format($bilan['retire'] / 100, 2, ',', ' ') . ' € retirés du portefeuille'
                                . ($bilan['deja_depense'] > 0 ? ' · ' . number_format($bilan['deja_depense'] / 100, 2, ',', ' ') . ' € avaient déjà été dépensés.' : '.'))
                            ->success()->send();
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListParrainages::route('/')];
    }
}
