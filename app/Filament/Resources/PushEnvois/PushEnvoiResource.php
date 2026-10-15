<?php

namespace App\Filament\Resources\PushEnvois;

use App\Filament\Resources\PushEnvois\Pages\ListPushEnvois;
use App\Models\PushEnvoi;
use App\Support\PushSuivi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Journal des notifications push : toutes celles qui partent (automatiques
 * et envoyées depuis l'admin), leur sort, et qui a cliqué dessus.
 */
class PushEnvoiResource extends Resource
{
    protected static ?string $model = PushEnvoi::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Notifications envoyées';

    protected static ?string $modelLabel = 'notification push';

    protected static ?string $pluralModelLabel = 'notifications push';

    protected static string | \UnitEnum | null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user:id,name,email'))
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m H:i')->sortable()
                    ->description(fn (PushEnvoi $r) => $r->statut === 'differe' && $r->prevu_at ? 'prévue ' . $r->prevu_at->format('d/m H:i') : null),
                TextColumn::make('type')->label('Type')
                    ->formatStateUsing(fn (?string $state) => PushSuivi::libelle($state))->wrap()
                    ->description(fn (PushEnvoi $r) => $r->origine === 'manuel' ? '✋ Envoyée par vous' : 'Automatique'),
                TextColumn::make('user.name')->label('Destinataire')
                    ->default(fn (PushEnvoi $r) => $r->estDiffusion() ? 'Tous les appareils' : '—')
                    ->description(fn (PushEnvoi $r) => $r->user?->email)
                    ->url(fn (PushEnvoi $r) => $r->user_id ? \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $r->user_id]) : null)
                    ->searchable(),
                TextColumn::make('titre')->label('Notification')->weight('bold')->limit(50)->wrap()
                    ->description(fn (PushEnvoi $r) => \Illuminate\Support\Str::limit((string) $r->message, 90))
                    ->searchable(['titre', 'message']),
                TextColumn::make('statut')->label('Sort')->badge()
                    ->formatStateUsing(fn (?string $state) => PushEnvoi::STATUTS[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'envoye' => 'success', 'differe', 'en_file' => 'warning', 'echec' => 'danger', default => 'gray',
                    })
                    ->tooltip(fn (PushEnvoi $r) => $r->raison)
                    ->description(fn (PushEnvoi $r) => $r->appareils ? $r->appareils_ok . '/' . $r->appareils . ' appareil(s)' : null),
                TextColumn::make('clics')->label('Ouverte ?')->sortable()
                    ->formatStateUsing(function ($state, PushEnvoi $r) {
                        if ($r->estDiffusion()) {
                            return $state . ' clic(s)' . ($r->tauxClic() !== null ? ' · ' . str_replace('.', ',', (string) $r->tauxClic()) . ' %' : '');
                        }

                        return $state > 0 ? '✅ Oui' : ($r->statut === 'envoye' ? 'Non' : '—');
                    })
                    ->description(fn (PushEnvoi $r) => $r->premier_clic_at && ! $r->estDiffusion()
                        ? 'après ' . $r->created_at->diffForHumans($r->premier_clic_at, true) : null)
                    ->color(fn ($state) => $state > 0 ? 'success' : null),
            ])
            ->filters([
                SelectFilter::make('origine')->label('Origine')->options(['auto' => 'Automatique', 'manuel' => 'Manuelle']),
                SelectFilter::make('type')->label('Type')->options(fn () => PushEnvoi::query()->distinct()->orderBy('type')->pluck('type')
                    ->filter()->mapWithKeys(fn ($t) => [$t => PushSuivi::libelle($t)])->all())->searchable(),
                SelectFilter::make('statut')->label('Sort')->options(PushEnvoi::STATUTS),
                TernaryFilter::make('ouverte')->label('Ouverte')
                    ->trueLabel('Cliquée')->falseLabel('Pas cliquée')
                    ->queries(
                        true: fn (Builder $query) => $query->where('clics', '>', 0),
                        false: fn (Builder $query) => $query->where('clics', 0)->where('statut', 'envoye'),
                        blank: fn (Builder $query) => $query,
                    ),
                Filter::make('periode')->label('Période')
                    ->schema([
                        DatePicker::make('du')->label('Du'),
                        DatePicker::make('au')->label('Au'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['du'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['au'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('clics')
                    ->label('Qui ?')
                    ->tooltip('Qui a cliqué')
                    ->icon('heroicon-o-cursor-arrow-rays')
                    ->color('gray')
                    ->visible(fn (PushEnvoi $r) => $r->clics > 0)
                    ->modalHeading(fn (PushEnvoi $r) => 'Clics : ' . $r->titre)
                    ->modalContent(fn (PushEnvoi $r) => view('filament.push.clics', ['envoi' => $r, 'clics' => $r->clicsDetail()->with('user:id,name,email')->limit(500)->get()]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
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
        return [
            'index' => ListPushEnvois::route('/'),
        ];
    }
}
