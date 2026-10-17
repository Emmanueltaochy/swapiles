<?php

namespace App\Support;

use App\Models\RelayPoint;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * Rapport d'impact d'un point relais, à envoyer chaque mois à l'enseigne
 * partenaire : colis, passages en magasin, articles réemployés, déchets et
 * CO2 évités. Aucune donnée personnelle (ni nom ni adresse de membre).
 */
class RapportPartenaire
{
    /** @return array<string,mixed> */
    public static function mois(RelayPoint $point, Carbon $mois): array
    {
        $debut = $mois->copy()->startOfMonth();

        return self::entre($point, $debut, $debut->copy()->addMonth()) + ['mois' => $debut];
    }

    /** @return array{colis:int,deposes:int,retires:int,passages:int,articles:int,kg:float,co2:float,km:int} */
    public static function entre(RelayPoint $point, ?Carbon $debut, ?Carbon $fin): array
    {
        $colis = Transaction::query()
            ->where('relay_point_id', $point->id)
            ->whereIn('status', TableauDeBord::STATUTS_VENDUS)
            ->when($debut, fn ($q) => $q->where('created_at', '>=', $debut))
            ->when($fin, fn ($q) => $q->where('created_at', '<', $fin))
            ->with(['listing' => fn ($q) => $q->withTrashed()->select('id', 'category_level1', 'weight_kg')])
            ->get(['id', 'listing_id', 'relay_status']);

        $deposes = $colis->whereIn('relay_status', ['deposited', 'collected'])->count();
        $retires = $colis->where('relay_status', 'collected')->count();
        $kg = $co2 = 0.0;
        foreach ($colis as $c) {
            $f = ImpactReemploi::facteurs($c->listing);
            $kg += $f['poids'];
            $co2 += $f['co2'];
        }

        return [
            'colis' => $colis->count(),
            'deposes' => $deposes,
            'retires' => $retires,
            // Chaque colis amène deux passages en magasin : le vendeur qui dépose, l'acheteur qui retire.
            'passages' => $deposes + $retires,
            'articles' => $colis->count(),
            'kg' => round($kg, 1),
            'co2' => round($co2),
            'km' => (int) round($co2 / max(0.01, (float) config('impact.co2_par_km_voiture', 0.2))),
        ];
    }

    /**
     * Les 6 derniers mois, plus le cumul depuis le début du partenariat.
     *
     * @return array{historique:array<int,array<string,mixed>>,cumul:array<string,mixed>}
     */
    public static function synthese(RelayPoint $point, Carbon $mois): array
    {
        $historique = [];
        for ($i = 5; $i >= 0; $i--) {
            $historique[] = self::mois($point, $mois->copy()->startOfMonth()->subMonths($i));
        }

        return [
            'historique' => $historique,
            'cumul' => self::entre($point, $point->partenaire_depuis?->copy()->startOfDay(), null),
        ];
    }
}
