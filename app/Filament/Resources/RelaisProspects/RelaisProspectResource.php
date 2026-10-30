<?php

namespace App\Filament\Resources\RelaisProspects;

use App\Filament\Resources\RelaisProspects\Pages\ListRelaisProspects;
use App\Models\RelaisProspect;
use App\Support\ProspectionRelais;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/** Prospection : commerces repérés qui pourraient devenir points relais. */
class RelaisProspectResource extends Resource
{
    use \App\Filament\Concerns\TitresEnFrancais;

    protected static ?string $model = RelaisProspect::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static ?string $navigationLabel = 'Prospection relais';

    protected static ?string $modelLabel = 'commerce à démarcher';

    protected static ?string $pluralModelLabel = 'commerces à démarcher';

    protected static string | \UnitEnum | null $navigationGroup = 'Points relais';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            // Les villes où il y a le plus d'annonces et de demandes de relais d'abord.
            ->defaultSort('demande', 'desc')
            ->columns([
                TextColumn::make('nom')->label('Commerce')->weight('bold')->searchable()->wrap()
                    ->description(fn (RelaisProspect $p) => ProspectionRelais::TYPES[$p->type] ?? $p->type),
                TextColumn::make('ville')->label('Ville')->searchable()->placeholder('—')
                    ->description(fn (RelaisProspect $p) => trim($p->adresse . ' · ' . $p->territoire, ' ·')),
                TextColumn::make('demande')->label('Demande')->sortable()
                    ->formatStateUsing(fn ($state) => (int) $state > 0 ? '🔥 ' . (int) $state : '—')
                    ->tooltip('Annonces en ligne dans la ville, + 20 par demande « un relais près de chez moi »'),
                TextColumn::make('email')->label('E-mail')->placeholder('pas trouvé')->copyable()->searchable()
                    ->description(fn (RelaisProspect $p) => $p->telephone),
                TextColumn::make('statut')->label('Suivi')->badge()
                    ->formatStateUsing(fn (?string $state) => RelaisProspect::STATUTS[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'nouveau' => 'warning', 'contacte' => 'info', 'interesse', 'relais' => 'success', 'refuse', 'desinscrit' => 'gray', default => 'gray',
                    })
                    ->description(fn (RelaisProspect $p) => $p->contacte_at ? 'Mail le ' . $p->contacte_at->format('d/m') : null),
            ])
            ->filters([
                SelectFilter::make('territoire')->label('Île')->options(array_combine(array_keys(ProspectionRelais::ILES), array_keys(ProspectionRelais::ILES))),
                SelectFilter::make('statut')->label('Suivi')->options(RelaisProspect::STATUTS),
                TernaryFilter::make('avec_email')->label('E-mail')
                    ->trueLabel('Avec e-mail')->falseLabel('Sans e-mail (à appeler)')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''),
                        false: fn ($q) => $q->where(fn ($q) => $q->whereNull('email')->orWhere('email', '')),
                        blank: fn ($q) => $q,
                    ),
                SelectFilter::make('type')->label('Type de commerce')->options(ProspectionRelais::TYPES),
            ])
            ->recordActions([
                Action::make('envoyer')
                    ->label('Envoyer le mail')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (RelaisProspect $p) => filled($p->email) && $p->statut === 'nouveau')
                    ->requiresConfirmation()
                    ->modalHeading(fn (RelaisProspect $p) => 'Envoyer la présentation à ' . $p->nom . ' ?')
                    ->modalDescription(fn (RelaisProspect $p) => 'Mail envoyé depuis contact@swapiles.com à ' . $p->email . ', avec un lien pour ne plus être contacté.')
                    ->action(function (RelaisProspect $p) {
                        $ok = ProspectionRelais::envoyer($p);
                        Notification::make()->title($ok ? 'Mail envoyé ✉️' : 'Pas envoyé : plafond du jour atteint (' . ProspectionRelais::plafondJour() . ').')
                            ->{$ok ? 'success' : 'warning'}()->send();
                    }),
                Action::make('appeler')
                    ->label('Appeler')
                    ->icon('heroicon-o-phone')
                    ->color('gray')
                    ->visible(fn (RelaisProspect $p) => filled($p->telephone))
                    ->url(fn (RelaisProspect $p) => 'tel:' . preg_replace('/[^0-9+]/', '', (string) $p->telephone)),
                Action::make('suivi')
                    ->label('Suivi')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->schema([
                        Select::make('statut')->label('Où en est-on ?')->options(RelaisProspect::STATUTS)->required(),
                        TextInput::make('email')->label('E-mail')->email()->maxLength(191),
                        TextInput::make('telephone')->label('Téléphone')->maxLength(40),
                        Textarea::make('notes')->label('Notes')->rows(3),
                    ])
                    ->fillForm(fn (RelaisProspect $p) => $p->only(['statut', 'email', 'telephone', 'notes']))
                    ->action(fn (RelaisProspect $p, array $data) => $p->update($data)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('envoyerSelection')
                        ->label('Envoyer le mail aux commerces cochés')
                        ->icon('heroicon-o-paper-airplane')
                        ->requiresConfirmation()
                        ->modalDescription(fn () => 'Seuls les commerces « À contacter » avec un e-mail le reçoivent, dans la limite de ' . ProspectionRelais::plafondJour() . ' mails par jour.')
                        ->action(function (Collection $records) {
                            $n = $records->filter(fn (RelaisProspect $p) => ProspectionRelais::envoyer($p))->count();
                            Notification::make()->title($n . ' mail(s) envoyé(s)')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = RelaisProspect::query()->contactable()->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRelaisProspects::route('/'),
        ];
    }
}
