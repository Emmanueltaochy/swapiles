<?php

namespace App\Filament\Pages;

use App\Models\Transaction;
use App\Support\SellerWallet;
use Filament\Pages\Page;

/**
 * Vue admin : part de l'activité qui passe HORS plateforme (espèces, dons,
 * échanges) vs paiements en ligne sécurisés. Mêmes règles d'argent que le
 * wallet vendeur (SellerWallet), pour un chiffre cohérent.
 */
class SalesByMode extends Page
{
    protected static ?string $navigationLabel = 'Ventes par mode';

    protected static ?string $title = 'Ventes par mode';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.sales-by-mode';

    public function getViewData(): array
    {
        $sales = Transaction::whereIn('status', ['paid', 'completed'])->get();

        $now = now();
        $month = $sales->filter(fn (Transaction $t) => $t->created_at
            && (int) $t->created_at->year === (int) $now->year
            && (int) $t->created_at->month === (int) $now->month);

        $modes = ['online', 'cash', 'gift', 'exchange', 'other'];
        $rows = [];

        foreach ($modes as $m) {
            $allOfMode = $sales->filter(fn (Transaction $t) => SellerWallet::mode($t) === $m);
            $monthOfMode = $month->filter(fn (Transaction $t) => SellerWallet::mode($t) === $m);

            // On masque les modes jamais utilisés (ex. « Autre » à 0).
            if ($allOfMode->isEmpty()) {
                continue;
            }

            $rows[] = [
                'mode' => $m,
                'label' => SellerWallet::modeLabel($m),
                'count_all' => $allOfMode->count(),
                'total_all' => round($allOfMode->sum(fn (Transaction $t) => SellerWallet::net($t)), 2),
                'count_month' => $monthOfMode->count(),
                'total_month' => round($monthOfMode->sum(fn (Transaction $t) => SellerWallet::net($t)), 2),
            ];
        }

        $totalAll = round($sales->sum(fn (Transaction $t) => SellerWallet::net($t)), 2);
        $securedAll = round(SellerWallet::securedOnly($sales)->sum(fn (Transaction $t) => SellerWallet::net($t)), 2);
        $offPlatform = round($totalAll - $securedAll, 2);
        $offShare = $totalAll > 0 ? round(($offPlatform / $totalAll) * 100) : 0;

        return [
            'rows' => $rows,
            'monthLabel' => $now->translatedFormat('F Y'),
            'totalAll' => $totalAll,
            'securedAll' => $securedAll,
            'offPlatform' => $offPlatform,
            'offShare' => $offShare,
        ];
    }
}
