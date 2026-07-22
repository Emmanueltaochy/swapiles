<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Models\Notification as UserNotification;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Stripe\StripeClient;

class TransactionsTable
{
    protected const STATUS_LABELS = [
        'pending' => 'En attente',
        'paid' => 'Payée',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
        'refunded' => 'Remboursée',
    ];

    protected const SHIPPING_LABELS = [
        'pending' => 'À expédier',
        'shipped' => 'Expédiée',
        'received' => 'Reçue',
        'hand_delivered' => 'Remise en main propre',
        'exchanged' => 'Échangée',
        'given' => 'Donnée',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('listing.title')
                    ->label('Annonce')
                    ->limit(28)
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('buyer.name')
                    ->label('Acheteur')
                    ->default('—')
                    ->searchable(),
                TextColumn::make('seller.name')
                    ->label('Vendeur')
                    ->default('—')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Montant')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', ' ') . ' €')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'paid' => 'info',
                        'completed' => 'success',
                        'refunded' => 'danger',
                        'cancelled' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('shipping_status')
                    ->label('Livraison')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::SHIPPING_LABELS[$state] ?? $state)
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('delivery_method')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'colissimo' ? '📦 Colissimo' : ($state === 'hand_delivery' ? '🤝 Main propre' : $state))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(self::STATUS_LABELS),
                SelectFilter::make('shipping_status')
                    ->label('Livraison')
                    ->options(self::SHIPPING_LABELS),
                SelectFilter::make('delivery_method')
                    ->label('Mode de livraison')
                    ->options([
                        'colissimo' => 'Colissimo',
                        'hand_delivery' => 'Main propre',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
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
