<?php

namespace App\Filament\Widgets\Tableau;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class DernieresVentes extends TableWidget
{
    protected static ?int $sort = 7;

    protected static ?string $heading = 'Dernières transactions';

    protected static bool $isDiscovered = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(Transaction::query()->with(['listing', 'buyer'])->latest()->limit(8))
            ->paginated(false)
            ->recordUrl(fn (Transaction $t) => TransactionResource::getUrl('view', ['record' => $t]))
            ->columns([
                TextColumn::make('listing.title')->label('Article')->limit(24)->placeholder('—')
                    ->description(fn (Transaction $t) => '→ ' . ($t->buyer->name ?? ($t->estDeclaree() ? 'acheteur inconnu' : '—'))),
                TextColumn::make('fiabilite')->label('Fiabilité')->badge()
                    ->state(fn (Transaction $t) => $t->fiabiliteLibelle())
                    ->color(fn (Transaction $t) => Transaction::FIABILITES[$t->fiabilite()][1]),
                TextColumn::make('amount')->label('Montant')->money('EUR', locale: 'fr'),
                TextColumn::make('status')->label('État')->badge()
                    ->formatStateUsing(fn (?string $s) => match ($s) {
                        'pending' => 'En attente', 'paid' => 'Payée', 'completed' => 'Terminée',
                        'refunded' => 'Remboursée', 'cancelled' => 'Annulée', default => (string) $s,
                    })
                    ->color(fn (?string $s) => match ($s) {
                        'paid' => 'warning', 'completed' => 'success', 'refunded', 'cancelled' => 'danger', default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Date')->since(),
            ]);
    }
}
