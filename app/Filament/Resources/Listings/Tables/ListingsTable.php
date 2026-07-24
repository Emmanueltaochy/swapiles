<?php

namespace App\Filament\Resources\Listings\Tables;

use App\Models\Listing;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListingsTable
{
    protected const TYPE_LABELS = [
        'achat' => 'Vente',
        'negoce-prix' => 'Négociable',
        'echange-produits' => 'Échange',
        'don' => 'Don',
        'location-vetements' => 'Location',
    ];

    protected const STATUS_LABELS = [
        'published' => 'En ligne',
        'draft' => 'Masquée',
        'sold' => 'Vendue',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('cover_image')
                    ->label('Photo')
                    ->getStateUsing(fn (Listing $record) => \App\Support\ImageUrl::absolute(optional($record->images()->orderBy('order')->first())->url))
                    ->checkFileExistence(false)
                    ->square()
                    ->size(48),
                TextColumn::make('title')
                    ->label('Annonce')
                    ->searchable()
                    ->weight('bold')
                    ->limit(32)
                    ->description(fn (Listing $record) => 'par ' . (optional($record->user)->name ?? '—')),
                TextColumn::make('price')
                    ->label('Prix')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', ' ') . ' €')
                    ->sortable(),
                TextColumn::make('listing_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::TYPE_LABELS[$state] ?? $state)
                    ->color('info'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'published' => 'success',
                        'sold' => 'warning',
                        'draft' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('territoire')
                    ->label('Île')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('views_count')
                    ->label('Vues')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Publiée le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(self::STATUS_LABELS),
                SelectFilter::make('listing_type')
                    ->label('Type')
                    ->options(self::TYPE_LABELS),
                SelectFilter::make('territoire')
                    ->label('Île')
                    ->options([
                        'La Réunion' => 'La Réunion',
                        'Martinique' => 'Martinique',
                        'Guadeloupe' => 'Guadeloupe',
                        'Guyane' => 'Guyane',
                        'Mayotte' => 'Mayotte',
                    ]),
                SelectFilter::make('payment')
                    ->label('Type de paiement')
                    ->options([
                        'cb_ok' => '🔒 Carte sécurisée (vendeur prêt)',
                        'cb_pending' => '⚠️ Carte activée · vendeur NON prêt',
                        'cash' => '💵 Espèces / main propre',
                        'exchange' => '🔄 Échange',
                        'don' => '🎁 Don',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $ready = fn (Builder $u) => $u->whereNotNull('stripe_account_id')
                            ->where('stripe_charges_enabled', true)
                            ->where('stripe_payouts_enabled', true);

                        return match ($data['value'] ?? null) {
                            'cb_ok' => $query->where('requires_online_payment', true)
                                ->whereHas('user', $ready),
                            'cb_pending' => $query->where('requires_online_payment', true)
                                ->whereDoesntHave('user', $ready),
                            'cash' => $query->where('allows_hand_delivery', true)
                                ->where('price', '>', 0)
                                ->whereIn('listing_type', ['achat', 'negoce-prix']),
                            'exchange' => $query->where(fn (Builder $q) => $q->where('allows_exchange', true)
                                ->orWhere('listing_type', 'echange-produits')),
                            'don' => $query->where(fn (Builder $q) => $q->where('listing_type', 'don')
                                ->orWhere('price', '<=', 0)),
                            default => $query,
                        };
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('photos')
                    ->label('Photos')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->url(fn (Listing $record) => route('admin.listing-photos.edit', $record))
                    ->openUrlInNewTab(),
                static::statusAction('publish', 'Remettre en ligne', 'heroicon-o-arrow-up-tray', 'success', 'published', fn (Listing $r) => $r->status !== 'published'),
                static::statusAction('hide', 'Masquer', 'heroicon-o-eye-slash', 'gray', 'draft', fn (Listing $r) => $r->status === 'published'),
                static::statusAction('markSold', 'Marquer vendue', 'heroicon-o-check-badge', 'warning', 'sold', fn (Listing $r) => $r->status !== 'sold'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    protected static function statusAction(string $name, string $label, string $icon, string $color, string $newStatus, \Closure $visible): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->visible($visible)
            ->action(function (Listing $record) use ($newStatus, $label) {
                $record->update(['status' => $newStatus]);

                FilamentNotification::make()
                    ->title('Annonce mise à jour : ' . $label)
                    ->success()
                    ->send();
            });
    }
}
