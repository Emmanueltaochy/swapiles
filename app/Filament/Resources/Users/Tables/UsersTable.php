<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
                IconColumn::make('is_pro')
                    ->label('Pro')
                    ->boolean()
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
                TextColumn::make('rating')
                    ->label('Note')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),
                TextColumn::make('transactions_count')
                    ->label('Ventes')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_banned')
                    ->label('Banni')
                    ->boolean()
                    ->trueIcon('heroicon-o-no-symbol')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('gray'),
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
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                static::toggleBanAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
                $record->update(['is_banned' => ! $record->is_banned]);

                FilamentNotification::make()
                    ->title($record->is_banned ? 'Membre banni' : 'Membre réactivé')
                    ->success()
                    ->send();
            });
    }
}
