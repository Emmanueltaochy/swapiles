<?php

namespace App\Filament\Resources\Negociations\Widgets;

use App\Support\Negociations;
use App\Support\TableauDeBord;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BilanNegociations extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = '30 derniers jours';

    /** 2 colonnes sur téléphone : les 4 chiffres tiennent sur un écran. */
    protected function getColumns(): int|array
    {
        return ['default' => 2, 'lg' => 4];
    }

    protected function getStats(): array
    {
        $b = Negociations::bilanOffres();
        $pct = fn (?int $v) => $v === null ? '—' : $v . ' %';

        return [
            Stat::make('Offres faites', number_format($b['offres'], 0, ',', ' '))
                ->description($b['en_attente'] . ' en attente de réponse')
                ->icon('heroicon-m-chat-bubble-left-right'),
            Stat::make('Acceptées par le vendeur', $pct($b['taux_acceptation']))
                ->description('des offres d’acheteurs')
                ->icon('heroicon-m-hand-thumb-up'),
            Stat::make('Payées ensuite', $pct($b['taux_paiement']))
                ->description($b['payees'] . ' vente(s) · ' . TableauDeBord::euros($b['volume_paye']))
                ->color('success')
                ->icon('heroicon-m-credit-card'),
            Stat::make('Remise moyenne', $b['remise_moyenne'] === null ? '—' : '−' . $b['remise_moyenne'] . ' %')
                ->description('par rapport au prix affiché')
                ->icon('heroicon-m-receipt-percent'),
        ];
    }
}
