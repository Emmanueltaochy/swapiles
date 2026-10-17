<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query
                ->withCount(['sales as ventes_count' => fn ($q) => $q->whereIn('status', ['paid', 'completed'])])
                ->withAvg('reviewsReceived', 'rating'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email),
                TextColumn::make('territoire')
                    ->label('Île')
                    ->badge()
                    ->color('info')
                    ->toggleable(),
                TextColumn::make('stripe_status')
                    ->label('Paiements')
                    ->badge()
                    ->getStateUsing(fn (User $record) => (
                        $record->stripe_account_id
                        && $record->stripe_charges_enabled
                        && $record->stripe_payouts_enabled
                        && $record->stripe_details_submitted
                    ) ? 'IBAN OK' : 'Non configuré')
                    ->color(fn (string $state) => $state === 'IBAN OK' ? 'success' : 'gray'),
                // Vrais chiffres (les anciennes colonnes rating / transactions_count n'étaient plus tenues à jour).
                TextColumn::make('ventes_count')
                    ->label('Ventes')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => (int) $state ?: '—'),
                TextColumn::make('reviews_received_avg_rating')
                    ->label('Note')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? '★ ' . number_format((float) $state, 1, ',', '') : '—'),
                TextColumn::make('statut_membre')
                    ->label('')
                    ->badge()
                    ->getStateUsing(fn (User $record) => $record->is_banned ? 'Banni' : ($record->is_pro ? 'Pro' : null))
                    ->color(fn (?string $state) => $state === 'Banni' ? 'danger' : 'warning'),
                TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_banned')
                    ->label('Bannis'),
                TernaryFilter::make('is_pro')
                    ->label('Comptes Pro'),
                SelectFilter::make('territoire')
                    ->label('Île')
                    ->options([
                        'La Réunion' => 'La Réunion',
                        'Martinique' => 'Martinique',
                        'Guadeloupe' => 'Guadeloupe',
                        'Guyane' => 'Guyane',
                        'Mayotte' => 'Mayotte',
                    ]),
                TernaryFilter::make('stripe_ready')
                    ->label('Paiement IBAN')
                    ->placeholder('Tous')
                    ->trueLabel('IBAN OK (configuré)')
                    ->falseLabel('Non configuré')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('stripe_account_id')
                            ->where('stripe_charges_enabled', true)
                            ->where('stripe_payouts_enabled', true),
                        false: fn (Builder $q) => $q->where(function (Builder $sub) {
                            $sub->whereNull('stripe_account_id')
                                ->orWhere('stripe_charges_enabled', false)
                                ->orWhere('stripe_payouts_enabled', false);
                        }),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                static::demanderNoteAction(),
                static::toggleBanAction(),
                static::deleteAndBlockAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('demanderNote')
                        ->label('⭐ Demander une note de l’appli')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Demander une note de l’appli')
                        ->modalDescription('Chaque membre sélectionné reçoit une notification (dans l’appli et sur son téléphone) qui ouvre la page d’avis du bon store. Ceux déjà sollicités ces ' . \App\Support\NoteAppli::DELAI_JOURS . ' derniers jours sont ignorés.')
                        ->modalSubmitActionLabel('Envoyer')
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $envoyes = $records->filter(fn (User $u) => \App\Support\NoteAppli::demander($u))->count();
                            FilamentNotification::make()
                                ->title($envoyes . ' demande(s) envoyée(s)')
                                ->body(($records->count() - $envoyes) > 0 ? ($records->count() - $envoyes) . ' membre(s) ignoré(s) : déjà sollicité(s) récemment ou banni(s).' : null)
                                ->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function demanderNoteAction(): Action
    {
        return Action::make('demanderNote')
            ->label('Demander une note')
            ->icon('heroicon-o-star')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => 'Demander à ' . $record->name . ' de noter l’appli ?')
            ->modalDescription('Une notification (appli + téléphone) ouvrira la page d’avis de l’App Store ou de Google Play.')
            ->modalSubmitActionLabel('Envoyer')
            ->action(function (User $record) {
                $ok = \App\Support\NoteAppli::demander($record);
                FilamentNotification::make()
                    ->title($ok ? 'Demande envoyée ⭐' : 'Pas envoyée : déjà sollicité ces ' . \App\Support\NoteAppli::DELAI_JOURS . ' derniers jours (ou banni).')
                    ->{$ok ? 'success' : 'warning'}()
                    ->send();
            });
    }

    protected static function toggleBanAction(): Action
    {
        return Action::make('toggleBan')
            ->label(fn (User $record) => $record->is_banned ? 'Réactiver' : 'Bannir')
            ->icon(fn (User $record) => $record->is_banned ? 'heroicon-o-lock-open' : 'heroicon-o-no-symbol')
            ->color(fn (User $record) => $record->is_banned ? 'success' : 'danger')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => $record->is_banned ? 'Réactiver ce membre ?' : 'Bannir ce membre ?')
            ->modalDescription(fn (User $record) => $record->is_banned
                ? $record->name . ' pourra de nouveau se connecter.'
                : $record->name . ' sera déconnecté et ne pourra plus accéder à son compte.')
            ->action(function (User $record) {
                $nowBanned = ! $record->is_banned;
                $record->update(['is_banned' => $nowBanned]);

                // Bannir = bloquer aussi l'e-mail (persiste même après suppression).
                // Réactiver = débloquer l'e-mail.
                if ($nowBanned) {
                    \App\Models\BlockedEmail::block($record->email, 'Membre banni', optional(auth()->user())->id);
                } else {
                    \App\Models\BlockedEmail::unblock($record->email);
                }

                FilamentNotification::make()
                    ->title($nowBanned ? 'Membre banni et e-mail bloqué' : 'Membre réactivé et e-mail débloqué')
                    ->success()
                    ->send();
            });
    }

    /**
     * Supprime le compte ET bloque définitivement son e-mail : impossible de se
     * réinscrire avec la même adresse, même après suppression du compte.
     */
    protected static function deleteAndBlockAction(): Action
    {
        return Action::make('deleteAndBlock')
            ->label('Supprimer + bloquer l’e-mail')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Supprimer ce membre et bloquer son e-mail ?')
            ->modalDescription(fn (User $record) => 'Le compte de ' . $record->name . ' sera supprimé et l’adresse '
                . $record->email . ' ne pourra plus jamais servir à s’inscrire ou se connecter.')
            ->modalSubmitActionLabel('Supprimer et bloquer')
            ->action(function (User $record) {
                \App\Models\BlockedEmail::block($record->email, 'Compte supprimé par un admin', optional(auth()->user())->id);
                $record->delete();

                FilamentNotification::make()
                    ->title('Membre supprimé, e-mail bloqué')
                    ->success()
                    ->send();
            });
    }
}
