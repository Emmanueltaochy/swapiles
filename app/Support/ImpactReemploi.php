<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Impact du réemploi : articles qui ont trouvé une seconde vie sur
 * Swap'Îles, kilos détournés des déchets et CO2 évité (estimations, voir
 * config/impact.php). Même définition d'un article vendu que le profil et
 * le classement : une fois par annonce, ventes contestées exclues.
 */
class ImpactReemploi
{
    public const TYPES = [
        'en_ligne' => '💳 Vendus en ligne',
        'main_propre' => '🤝 Vendus en main propre',
        'echange' => '🔄 Échangés',
        'don' => '🎁 Donnés',
        'ancien' => '✅ Vendus (avant le 12/10/2026)',
    ];

    /**
     * Une ligne par article réemployé.
     *
     * @return Collection<int,array{date:Carbon,type:string,ile:string,categorie:?string,poids:float,co2:float,relais:bool}>
     */
    public static function articles(?Carbon $depuis = null): Collection
    {
        $lignes = collect();
        $vus = [];

        $ventes = Transaction::query()
            ->whereIn('status', TableauDeBord::STATUTS_VENDUS)
            ->whereNull('acheteur_conteste_at')
            ->whereNotNull('listing_id')
            ->with(['listing' => fn ($q) => $q->withTrashed()->select('id', 'territoire', 'category_level1', 'weight_kg')])
            ->orderBy('created_at')
            ->get(['id', 'listing_id', 'payment_method', 'delivery_method', 'created_at']);

        foreach ($ventes as $vente) {
            if (isset($vus[$vente->listing_id])) {
                continue;
            }
            $vus[$vente->listing_id] = true;
            $type = match ($vente->payment_method) {
                'echange' => 'echange',
                'don' => 'don',
                'especes' => 'main_propre',
                default => 'en_ligne',
            };
            $lignes->push(self::ligne($vente->created_at, $type, $vente->listing, $vente->delivery_method === 'relay'));
        }

        // Annonces closes avec l'ancien bouton « Vendue », sans vente enregistrée.
        Listing::query()->where('status', 'sold')->whereNotIn('id', array_keys($vus) ?: [0])
            ->get(['id', 'territoire', 'category_level1', 'weight_kg', 'updated_at'])
            ->each(fn (Listing $l) => $lignes->push(self::ligne($l->updated_at, 'ancien', $l, false)));

        return $depuis ? $lignes->filter(fn ($l) => $l['date']->gte($depuis))->values() : $lignes;
    }

    /**
     * Poids (kg) et CO2 évité (kg CO2e) estimés pour un article.
     *
     * @return array{poids:float,co2:float}
     */
    public static function facteurs(?Listing $annonce): array
    {
        $categorie = $annonce?->category_level1 ? mb_strtolower($annonce->category_level1) : null;
        $facteurs = config('impact.categories.' . $categorie) ?? config('impact.defaut');

        return [
            'poids' => (float) ($annonce?->weight_kg ?: $facteurs['poids']),
            'co2' => (float) $facteurs['co2'],
        ];
    }

    private static function ligne(?Carbon $date, string $type, ?Listing $annonce, bool $relais): array
    {
        $categorie = $annonce?->category_level1 ? mb_strtolower($annonce->category_level1) : null;
        $facteurs = self::facteurs($annonce);
        $poids = $facteurs['poids'];

        return [
            'date' => $date ?? now(),
            'type' => $type,
            'ile' => $annonce?->territoire ?: 'Non précisée',
            'categorie' => $categorie,
            'poids' => $poids,
            'co2' => (float) $facteurs['co2'],
            'relais' => $relais,
        ];
    }

    /** @return array<string,mixed> */
    public static function bilan(?Carbon $depuis = null): array
    {
        $articles = self::articles($depuis);
        $resume = fn (Collection $c) => [
            'articles' => $c->count(),
            'kg' => round($c->sum('poids'), 1),
            'co2' => round($c->sum('co2')),
        ];

        $parMois = [];
        $debut = now()->startOfMonth()->subMonths(11);
        for ($m = $debut->copy(); $m->lte(now()); $m->addMonth()) {
            $fin = $m->copy()->addMonth();
            $parMois[] = ['mois' => $m->copy()] + $resume($articles->filter(fn ($l) => $l['date']->gte($m) && $l['date']->lt($fin)));
        }

        $libellesCategories = collect(Categories::ARBRE)->map(fn ($c) => $c['label'])->all();

        return [
            'total' => $resume($articles),
            'km_voiture' => (int) round($articles->sum('co2') / max(0.01, (float) config('impact.co2_par_km_voiture', 0.2))),
            'par_type' => collect(self::TYPES)->map(fn ($libelle, $cle) => ['libelle' => $libelle] + $resume($articles->where('type', $cle)))->filter(fn ($l) => $l['articles'] > 0)->all(),
            'par_ile' => $articles->groupBy('ile')->map($resume)->sortByDesc('articles')->all(),
            'par_categorie' => $articles->groupBy(fn ($l) => $libellesCategories[$l['categorie']] ?? 'Autre / non précisée')->map($resume)->sortByDesc('co2')->all(),
            'par_mois' => $parMois,
            'via_relais' => $articles->where('relais', true)->count(),
        ];
    }

    /** Fichier CSV (« ; », Excel en français). */
    public static function csv(array $bilan): string
    {
        $nombre = fn ($v) => str_replace('.', ',', (string) $v);
        $sortie = "\u{FEFF}\"Mois\";\"Articles réemployés\";\"Kg détournés (estimation)\";\"Kg CO2e évités (estimation)\"\r\n";
        foreach ($bilan['par_mois'] as $m) {
            $sortie .= '"' . ucfirst($m['mois']->locale('fr')->translatedFormat('F Y')) . '";"' . $m['articles'] . '";"' . $nombre($m['kg']) . '";"' . $m['co2'] . "\"\r\n";
        }

        return $sortie;
    }
}
