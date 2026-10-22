<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\RelayPoint;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;

/**
 * Dossier PDF à envoyer aux entreprises (RSE, points relais, partenaires) :
 * présentation, chiffres réels du jour, impact du réemploi et formules.
 * Uniquement des totaux anonymes, aucune donnée personnelle.
 */
class DossierPartenaire
{
    /** Façons dont un article a trouvé preneur (libellés sans emoji pour le PDF). */
    public const TYPES = [
        'en_ligne' => 'Vendus avec paiement en ligne',
        'main_propre' => 'Vendus en main propre',
        'echange' => 'Échangés',
        'don' => 'Donnés',
        'ancien' => 'Vendus (avant le suivi détaillé)',
    ];

    /** @return array<string,mixed> */
    public static function donnees(?string $ile = null): array
    {
        $ile = TerritoireContext::isValid($ile) ? $ile : null;
        $membres = MembresIndicateurs::chiffres();
        $articles = ImpactReemploi::articles();
        $libellesCategories = collect(Categories::ARBRE)->map(fn ($c) => $c['label'])->all();

        $croissance = array_slice(Croissance::mois(6), -6);

        return [
            'date' => now(),
            'ile' => $ile,
            'contact' => config('partenaires.contact'),
            'formules' => config('partenaires.formules'),

            'membres' => $membres['total'],
            'nouveaux_30j' => $membres['nouveaux_30j'],
            'par_ile' => array_values(array_filter($membres['par_ile'], fn ($l) => TerritoireContext::isValid($l['ile']))),
            'iles' => count(array_filter($membres['par_ile'], fn ($l) => TerritoireContext::isValid($l['ile']) && $l['membres'] > 0)),
            'annonces' => Listing::where('status', 'published')->count(),
            'installations' => InstallationsAppli::chiffres()['toutes']['total'],
            'points_relais' => RelayPoint::active()->count(),
            'partenaires' => RelayPoint::active()->whereNotNull('partenaire_enseigne')->where('partenaire_enseigne', '!=', '')->count(),

            'impact' => self::resume($articles),
            'impact_30j' => self::resume($articles->filter(fn ($l) => $l['date']->gte(now()->subDays(30)))),
            'par_type' => $articles->groupBy('type')
                ->map(fn ($c, $type) => ['libelle' => self::TYPES[$type] ?? 'Autre'] + self::resume($c))
                ->sortByDesc('articles')->values()->all(),
            'par_categorie' => $articles->groupBy(fn ($l) => $libellesCategories[$l['categorie']] ?? 'Autre / non précisée')
                ->map(fn ($c, $nom) => ['libelle' => $nom] + self::resume($c))
                ->sortByDesc('co2')->take(6)->values()->all(),
            'impact_par_ile' => $articles->groupBy('ile')
                ->filter(fn ($c, $nom) => TerritoireContext::isValid($nom))
                ->map(fn ($c, $nom) => ['libelle' => $nom] + self::resume($c))
                ->sortByDesc('articles')->values()->all(),
            'croissance' => array_map(fn ($m) => [
                'libelle' => $m['libelle'],
                'en_cours' => $m['en_cours'],
                'inscriptions' => $m['inscriptions'],
                'annonces' => $m['annonces'],
                'ventes' => $m['ventes'] + $m['ventes_declarees'],
            ], $croissance),

            'focus' => $ile ? self::focus($ile, $membres['par_ile'], $articles) : null,
        ];
    }

    /** Chiffres d'une seule île, pour la page « Focus ». */
    private static function focus(string $ile, array $parIle, Collection $articles): array
    {
        $membres = collect($parIle)->firstWhere('ile', $ile) ?? ['membres' => 0, 'nouveaux' => 0];

        return [
            'membres' => $membres['membres'],
            'nouveaux_30j' => $membres['nouveaux'],
            'annonces' => Listing::where('status', 'published')->where('territoire', $ile)->count(),
            'points_relais' => RelayPoint::active()->where('territoire', $ile)->count(),
            'impact' => self::resume($articles->where('ile', $ile)),
        ];
    }

    /** @return array{articles:int,kg:float,co2:float,km:int} */
    private static function resume(Collection $articles): array
    {
        $co2 = (float) $articles->sum('co2');

        return [
            'articles' => $articles->count(),
            'kg' => round((float) $articles->sum('poids'), 1),
            'co2' => round($co2),
            'km' => (int) round($co2 / max(0.01, (float) config('impact.co2_par_km_voiture', 0.2))),
        ];
    }

    public static function pdf(?string $ile = null): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->setChroot([public_path(), base_path('vendor/dompdf/dompdf')]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.dossier-partenaires', self::donnees($ile) + [
            'logo' => public_path('images/logo.png'),
        ])->render(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    public static function nomFichier(?string $ile = null): string
    {
        $suffixe = TerritoireContext::isValid($ile) ? '-' . \Illuminate\Support\Str::slug($ile) : '';

        return 'swapiles-dossier-partenaires' . $suffixe . '-' . now()->format('Y-m-d') . '.pdf';
    }
}
