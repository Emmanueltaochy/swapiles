<?php

namespace App\Filament\Resources\Echanges\Widgets;

use App\Support\Negociations;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BilanEchanges extends StatsOverviewWidget
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
        $b = Negociations::bilanEchanges();

        return [
            Stat::make('Propositions', number_format($b['propositions'], 0, ',', ' '))
                ->icon('heroicon-m-arrows-right-left'),
            Stat::make('En attente', number_format($b['en_attente'], 0, ',', ' '))
                ->description('réponse du vendeur attendue')
                ->color('warning')
                ->icon('heroicon-m-clock'),
            Stat::make('Acceptées', $b['taux_acceptation'] === null ? '—' : $b['taux_acceptation'] . ' %')
                ->description($b['acceptees'] . ' proposition(s)')
                ->icon('heroicon-m-hand-thumb-up'),
            Stat::make('Échanges conclus', number_format($b['conclus'], 0, ',', ' '))
                ->description('déclarés par le vendeur')
                ->color('success')
                ->icon('heroicon-m-check-badge'),
        ];
    }
}
