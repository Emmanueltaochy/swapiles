<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Support\CsvExport;
use Filament\Actions\CreateAction;
use App\Models\Transaction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    /**
     * Onglets : les ventes réussies d'abord, les paiements abandonnés et
     * annulations à part (ils noyaient les vraies ventes).
     */
    public function getTabs(): array
    {
        $onglet = fn (string $libelle, ?array $statuts, string $icone) => Tab::make($libelle)
            ->icon($icone)
            ->badge(fn () => $statuts ? Transaction::whereIn('status', $statuts)->count() : Transaction::count())
            ->modifyQueryUsing(fn (Builder $query) => $statuts ? $query->whereIn('status', $statuts) : $query);

        return [
            'reussies' => $onglet('Réussies', ['paid', 'completed'], 'heroicon-m-check-circle')->badgeColor('success'),
            'en_cours' => $onglet('Paiement en cours', ['pending'], 'heroicon-m-clock')->badgeColor('warning'),
            'annulees' => $onglet('Annulées', ['cancelled'], 'heroicon-m-x-circle')->badgeColor('gray'),
            'remboursees' => $onglet('Remboursées', ['refunded'], 'heroicon-m-arrow-uturn-left')->badgeColor('danger'),
            'toutes' => $onglet('Toutes', null, 'heroicon-m-queue-list')->badgeColor('gray'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'reussies';
    }

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
