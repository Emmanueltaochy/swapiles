<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\Tables\TransactionsTable;
use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    public function getTitle(): string
    {
        return 'Vente n° ' . $this->record->id . ' · ' . ($this->record->listing->title ?? 'annonce supprimée');
    }

    public function getSubheading(): ?string
    {
        $t = $this->record;

        return trim(($t->seller->name ?? 'Vendeur inconnu') . ' → ' . ($t->buyer->name ?? $t->buyer_full_name ?? 'acheteur inconnu'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('annonce')
                ->label("Voir l'annonce")
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $this->record->listing ? route('listings.show', $this->record->listing) : null, shouldOpenInNewTab: true)
                ->visible(fn () => $this->record->listing !== null),
            TransactionsTable::refundAction()->record($this->record)->outlined(),
            EditAction::make()->color('gray'),
        ];
    }
}
