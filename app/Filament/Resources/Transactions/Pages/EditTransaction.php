<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    public function getTitle(): string
    {
        return 'Corriger la vente n° ' . $this->record->id;
    }

    public function getSubheading(): ?string
    {
        return 'Seuls le mode de paiement, le statut et la date de fin se corrigent ici.';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
