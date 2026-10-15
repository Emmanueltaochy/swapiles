<?php

namespace App\Filament\Widgets\Tableau;

use App\Support\TableauDeBord;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Les chiffres d'activité sur la période choisie en haut de page. */
class PeriodeStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    // Pas de rafraîchissement automatique : ces chiffres bougent peu.
    protected ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected function getHeading(): ?string
    {
        return ucfirst(TableauDeBord::PERIODES[$this->periode()] ?? '30 derniers jours');
    }

    private function periode(): string
    {
        return $this->pageFilters['periode'] ?? '30j';
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $p = TableauDeBord::periode($this->periode());

        return [
            Stat::make('Ventes payées en ligne', TableauDeBord::euros($p['volume']))
                ->description($p['ventes'] . ' vente(s) · certaines')
                ->icon('heroicon-m-banknotes'),
            Stat::make('Main propre déclarée', TableauDeBord::euros($p['volume_declare']))
                ->description($p['ventes_declarees'] . ' déclarée(s) · ' . $p['ventes_declarees_confirmees'] . " confirmée(s) par l'acheteur")
                ->color('gray')
                ->icon('heroicon-m-hand-raised')
                ->url(\App\Filament\Resources\Transactions\TransactionResource::getUrl('index', ['filters' => ['fiabilite' => ['value' => 'declaree_inconnu']]])),
            Stat::make("Revenus Swap'Îles", TableauDeBord::euros($p['revenus']))
                ->description('Protection + part relais, crédit parrainage déduit')
                ->color('success')
                ->icon('heroicon-m-currency-euro'),
            Stat::make('Panier moyen', TableauDeBord::euros($p['panier_moyen']))
                ->description('ventes payées en ligne')
                ->icon('heroicon-m-shopping-cart'),
            Stat::make('Nouveaux membres', number_format($p['inscriptions'], 0, ',', ' '))
                ->icon('heroicon-m-user-plus'),
            Stat::make('Annonces publiées', number_format($p['annonces'], 0, ',', ' '))
                ->description(number_format($p['annonces_en_ligne'], 0, ',', ' ') . ' en ligne actuellement')
                ->icon('heroicon-m-shopping-bag'),
            Stat::make('Messages échangés', number_format($p['messages'], 0, ',', ' '))
                ->icon('heroicon-m-chat-bubble-left-right'),
        ];
    }
}
