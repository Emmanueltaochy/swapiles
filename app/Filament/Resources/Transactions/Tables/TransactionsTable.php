<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Models\Notification as UserNotification;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Stripe\StripeClient;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sharetribe_id')
                    ->searchable(),
                TextColumn::make('listing_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('seller_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('buyer_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('buyer_protection_fee')
                    ->label('Protection acheteur')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('commission')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('payment_method')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('stripe_payment_intent_id')
                    ->searchable(),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                static::refundAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Action « Rembourser l'acheteur ».
     * Sécurité : visible uniquement si un paiement Stripe existe ET que le
     * vendeur n'a PAS encore été payé (pas de transfert/versement), pour éviter
     * que la plateforme rembourse alors qu'elle a déjà versé le vendeur.
     */
    protected static function refundAction(): Action
    {
        return Action::make('refund')
            ->label('Rembourser')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Rembourser l\'acheteur ?')
            ->modalDescription(fn (Transaction $record) => 'Rembourser '
                . number_format((float) $record->amount, 2, ',', ' ')
                . ' € à l\'acheteur pour la vente #' . $record->id
                . '. Cette action est irréversible.')
            ->modalSubmitActionLabel('Rembourser')
            ->visible(fn (Transaction $record) => in_array($record->status, ['paid', 'completed'], true)
                && ! empty($record->stripe_payment_intent_id)
                && empty($record->stripe_transfer_id)
                && empty($record->released_at))
            ->action(function (Transaction $record) {
                if (! empty($record->stripe_transfer_id) || ! empty($record->released_at)) {
                    FilamentNotification::make()
                        ->title('Impossible : le vendeur a déjà été payé.')
                        ->danger()->send();

                    return;
                }

                try {
                    $stripe = new StripeClient(env('STRIPE_SECRET'));

                    $refund = $stripe->refunds->create([
                        'payment_intent' => $record->stripe_payment_intent_id,
                        'metadata' => [
                            'transaction_id' => $record->id,
                            'reason' => 'remboursement_admin',
                        ],
                    ]);

                    $record->update(['status' => 'refunded']);

                    if ($record->listing && $record->listing->status === 'sold') {
                        $record->listing->update(['status' => 'published']);
                    }

                    if ($record->buyer_id) {
                        UserNotification::create([
                            'user_id' => $record->buyer_id,
                            'type' => 'transaction_refunded',
                            'title' => 'Remboursement effectué 💶',
                            'message' => 'Votre achat "' . ($record->listing->title ?? 'Annonce')
                                . '" a été remboursé (' . number_format((float) $record->amount, 2, ',', ' ') . ' €).',
                            'url' => route('account.transactions.show', $record),
                        ]);
                    }

                    FilamentNotification::make()
                        ->title('Remboursement effectué ✅')
                        ->body('Stripe refund : ' . $refund->id)
                        ->success()->send();
                } catch (\Throwable $e) {
                    report($e);

                    FilamentNotification::make()
                        ->title('Échec du remboursement')
                        ->body($e->getMessage())
                        ->danger()->send();
                }
            });
    }
}
