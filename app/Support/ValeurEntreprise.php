<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estimation PRUDENTE de la valeur de Swap'Îles à partir des vrais chiffres
 * (revenus encaissés, croissance, membres actifs). Deux méthodes, on garde la
 * plus haute des deux pour chaque borne :
 *  - revenus : rythme annuel (3 derniers mois complets × 4) × un multiple
 *    qui dépend de la croissance ;
 *  - actifs : technologie + membres actifs, quand les revenus sont encore
 *    trop faibles pour porter la valeur.
 * Hypothèses dans config/valeur.php. Les charges ne sont pas connues ici :
 * l'estimation ne tient pas compte de la rentabilité.
 */
class ValeurEntreprise
{
    /** @return array<string,mixed> */
    public static function estimation(): array
    {
        $debutMois = now()->startOfMonth();
        $trimestre = (float) TableauDeBord::entre($debutMois->copy()->subMonths(3), $debutMois)['revenus'];
        $trimestrePrecedent = (float) TableauDeBord::entre($debutMois->copy()->subMonths(6), $debutMois->copy()->subMonths(3))['revenus'];
        $douzeMois = (float) TableauDeBord::entre($debutMois->copy()->subMonths(12), $debutMois)['revenus'];
        $trenteJours = TableauDeBord::entre(now()->subDays(30), null);

        $rythmeAnnuel = round(max(0.0, $trimestre) * 4, 2);
        $croissance = Croissance::evolution($trimestre, $trimestrePrecedent);
        $multiple = self::multiple($croissance);

        $actifs = self::membresActifs();
        $techno = config('valeur.technologie', [15000, 40000]);
        $parMembre = config('valeur.par_membre_actif', [1, 3]);

        $parRevenus = [
            'bas' => self::arrondi($rythmeAnnuel * $multiple['bas']),
            'haut' => self::arrondi($rythmeAnnuel * $multiple['haut']),
        ];
        $parActifs = [
            'bas' => self::arrondi($techno[0] + $actifs * $parMembre[0]),
            'haut' => self::arrondi($techno[1] + $actifs * $parMembre[1]),
        ];

        $ventesEnLigne = (int) $trenteJours['ventes'];
        $ventesDeclarees = (int) $trenteJours['ventes_declarees'];

        return [
            'fourchette' => [
                'bas' => max($parRevenus['bas'], $parActifs['bas']),
                'haut' => max($parRevenus['haut'], $parActifs['haut']),
            ],
            'methode' => $parRevenus['haut'] > $parActifs['haut'] ? 'revenus' : 'actifs',
            'par_revenus' => $parRevenus,
            'par_actifs' => $parActifs,
            'revenus' => [
                'trente_jours' => round((float) $trenteJours['revenus'], 2),
                'trimestre' => round($trimestre, 2),
                'trimestre_precedent' => round($trimestrePrecedent, 2),
                'douze_mois' => round($douzeMois, 2),
                'rythme_annuel' => $rythmeAnnuel,
                'croissance' => $croissance,
            ],
            'multiple' => $multiple,
            'hypotheses' => ['technologie' => $techno, 'par_membre_actif' => $parMembre],
            'membres' => User::count(),
            'membres_actifs' => $actifs,
            'annonces_en_ligne' => Listing::where('status', 'published')->count(),
            'ventes_en_ligne_30j' => $ventesEnLigne,
            'ventes_declarees_30j' => $ventesDeclarees,
            'part_en_ligne' => ($ventesEnLigne + $ventesDeclarees) > 0
                ? (int) round($ventesEnLigne / ($ventesEnLigne + $ventesDeclarees) * 100)
                : null,
            // Ce que rapporte 1 000 € de revenus en plus chaque mois, au multiple actuel.
            'effet_1000_par_mois' => [
                'bas' => self::arrondi(12000 * $multiple['bas']),
                'haut' => self::arrondi(12000 * $multiple['haut']),
            ],
        ];
    }

    /** @return array{bas:float,haut:float,libelle:string} */
    public static function multiple(?int $croissance): array
    {
        foreach (config('valeur.multiples', []) as $palier) {
            $min = $palier['croissance_min'];
            if ($min === null || ($croissance !== null && $croissance >= $min)) {
                return ['bas' => (float) $palier['bas'], 'haut' => (float) $palier['haut'], 'libelle' => $palier['libelle']];
            }
        }

        return ['bas' => 0.5, 'haut' => 1.0, 'libelle' => 'revenus en baisse ou trop récents pour juger'];
    }

    /** Membres connectés venus sur le site ou l'appli ces 30 derniers jours. */
    public static function membresActifs(): int
    {
        if (! Schema::hasTable('visiteurs_jours')) {
            return 0;
        }

        return (int) DB::table('visiteurs_jours')
            ->where('jour', '>=', now()->subDays(30)->toDateString())
            ->whereNotNull('user_id')
            ->distinct()->count('user_id');
    }

    /** Arrondi au millier inférieur : une estimation n'a pas de centimes. */
    private static function arrondi(float $montant): int
    {
        return (int) (floor(max(0.0, $montant) / 1000) * 1000);
    }
}
