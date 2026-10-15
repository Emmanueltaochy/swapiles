<?php

namespace App\Filament\Widgets\Parrainage;

use App\Models\Parrainage;
use App\Models\ParrainageEvenement;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ParrainageStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $parStatut = Parrainage::query()->selectRaw('statut, COUNT(*) as n')->groupBy('statut')->pluck('n', 'statut');
        $verse = (int) Parrainage::where('statut', 'valide')->sum('montant_centimes');
        $partages = ParrainageEvenement::where('evenement', 'referral_link_shared')->where('created_at', '>=', now()->subDays(30))->count();
        $ouverts = ParrainageEvenement::where('evenement', 'referral_link_opened')->where('created_at', '>=', now()->subDays(30))->count();

        return [
            Stat::make('Filleuls inscrits', (int) $parStatut->sum())
                ->description(($parStatut['inscrit'] ?? 0) . ' sans achat · ' . ($parStatut['achat_en_cours'] ?? 0) . ' 1er achat en attente'),
            Stat::make('Parrainages validés', (int) ($parStatut['valide'] ?? 0))
                ->description(number_format($verse / 100, 2, ',', ' ') . ' € de crédit versé')->color('success'),
            Stat::make('Refusés / annulés', (int) (($parStatut['refuse'] ?? 0) + ($parStatut['annule'] ?? 0)))
                ->description('voir le motif dans la liste')->color('danger'),
            Stat::make('Liens partagés (30 j)', $partages)->description($ouverts . ' ouverture(s) de lien'),
        ];
    }
}
