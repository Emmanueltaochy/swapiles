<?php

namespace App\Filament\Widgets\Tableau;

use App\Filament\Pages\LiveVisitors;
use App\Support\TableauDeBord;
use App\Support\VisiteursEnDirect;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** La journée en cours : qui est là, ce qui se passe, comparé à hier. */
class AujourdhuiStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = "Aujourd'hui";

    protected ?string $pollingInterval = '30s';

    protected static bool $isDiscovered = false;

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $direct = VisiteursEnDirect::compte();
        $visiteurs = TableauDeBord::visiteursParJour(14);
        $membres = TableauDeBord::serieJournaliere(TableauDeBord::inscriptions());
        $annonces = TableauDeBord::serieJournaliere(TableauDeBord::annonces());
        $ventes = TableauDeBord::serieJournaliere(TableauDeBord::ventes());

        return [
            Stat::make('En ligne maintenant', $direct['humains'])
                ->description('dont ' . $direct['membres'] . ' membre(s), ' . $direct['appli'] . " dans l'appli")
                ->descriptionIcon('heroicon-m-signal')
                ->color('success')
                ->url(LiveVisitors::getUrl()),
            $this->stat('Visiteurs uniques', $visiteurs, 'heroicon-m-users'),
            $this->stat('Nouveaux membres', $membres, 'heroicon-m-user-plus'),
            $this->stat('Nouvelles annonces', $annonces, 'heroicon-m-shopping-bag'),
            $this->stat('Ventes payées en ligne', $ventes, 'heroicon-m-banknotes'),
        ];
    }

    private function stat(string $libelle, array $serie, string $icone): Stat
    {
        $evolution = TableauDeBord::evolution($serie);
        $hier = $serie[count($serie) - 2] ?? 0;

        $description = $evolution === null
            ? 'hier : ' . $hier
            : ($evolution >= 0 ? '+' : '') . $evolution . ' % vs hier (' . $hier . ')';

        return Stat::make($libelle, end($serie))
            ->description($description)
            ->descriptionIcon($evolution !== null && $evolution < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
            ->color($evolution !== null && $evolution < 0 ? 'danger' : 'success')
            ->chart($serie)
            ->icon($icone);
    }
}
