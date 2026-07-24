<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Support\CsvExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            CsvExport::action('transactions', [
                'ID' => fn ($t) => $t->id,
                'Date' => fn ($t) => $t->created_at,
                'Annonce' => fn ($t) => $t->listing?->title,
                'Acheteur' => fn ($t) => $t->buyer?->name,
                'Email acheteur' => fn ($t) => $t->buyer?->email,
                'Vendeur' => fn ($t) => $t->seller?->name,
                'Email vendeur' => fn ($t) => $t->seller?->email,
                'Montant (€)' => fn ($t) => $t->amount,
                'Commission (€)' => fn ($t) => $t->commission,
                'Part vendeur (€)' => fn ($t) => $t->seller_amount,
                'Moyen de paiement' => fn ($t) => $t->payment_method,
                'Livraison' => fn ($t) => $t->delivery_method,
                'Statut' => fn ($t) => $t->status,
                'Statut livraison' => fn ($t) => $t->shipping_status,
                'Suivi' => fn ($t) => $t->tracking_number,
            ], fn () => $this->getFilteredTableQuery()->with(['listing', 'buyer', 'seller'])->get()),
        ];
    }
}
