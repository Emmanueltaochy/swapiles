<?php

namespace App\Filament\Widgets\Tableau;

use App\Filament\Resources\FlaggedMessages\FlaggedMessageResource;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Support\TableauDeBord;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ce qui attend une action de l'équipe : en rouge tant que ce n'est pas traité. */
class ATraiterStats extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'À traiter';

    protected ?string $description = 'Chaque case mène à la liste correspondante.';

    protected ?string $pollingInterval = '60s';

    protected static bool $isDiscovered = false;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $n = TableauDeBord::aTraiter();

        return [
            $this->alerte('Signalements à examiner', $n['signalements'], 'heroicon-m-shield-exclamation', ReportResource::getUrl()),
            $this->alerte('Messages signalés (30 j)', $n['messages_signales'], 'heroicon-m-flag', FlaggedMessageResource::getUrl()),
            $this->alerte('Ventes à vérifier', $n['ventes_a_verifier'], 'heroicon-m-exclamation-triangle', TransactionResource::getUrl()),
            $this->alerte('Annonces masquées (sans photo)', $n['annonces_sans_photo'], 'heroicon-m-photo', ListingResource::getUrl(), 'warning'),
        ];
    }

    private function alerte(string $libelle, int $nombre, string $icone, string $url, string $couleur = 'danger'): Stat
    {
        return Stat::make($libelle, $nombre)
            ->description($nombre === 0 ? 'Rien en attente' : 'À traiter')
            ->descriptionIcon($nombre === 0 ? 'heroicon-m-check-circle' : 'heroicon-m-arrow-right')
            ->color($nombre === 0 ? 'success' : $couleur)
            ->icon($icone)
            ->url($url);
    }
}
