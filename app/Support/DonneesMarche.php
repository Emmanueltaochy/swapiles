<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Données de valeur de Swap'Îles : croissance (ce que regarde un acheteur de
 * l'entreprise) et tendances du marché de l'occasion dans les îles (ce qui
 * intéresse marques, enseignes et études).
 *
 * UNIQUEMENT des chiffres agrégés et anonymes : aucun nom, e-mail, adresse ni
 * historique individuel. Toute ligne représentant moins de SEUIL_ANONYMAT
 * membres ou annonces est masquée, pour qu'on ne puisse reconnaître personne.
 * C'est ce que le RGPD permet de partager sans consentement individuel.
 */
class DonneesMarche
{
    /** En dessous, une ligne pourrait désigner quelqu'un : on ne l'affiche pas. */
    public const SEUIL_ANONYMAT = 5;

    /** Ventes « qui ont trouvé preneur », argent ou non. */
    private const STATUTS_CONCLUS = ['paid', 'completed'];

    // --- Croissance ------------------------------------------------------------

    /** @return array<string,mixed> */
    public static function croissance(): array
    {
        $enLigne = Transaction::query()->whereIn('status', TableauDeBord::STATUTS_VENDUS)->enLigne();
        $gmv = (float) (clone $enLigne)->sum('amount');
        $revenus = self::revenus(clone $enLigne);
        $ventesEnLigne = (clone $enLigne)->count();

        $acheteurs = Transaction::query()->whereIn('status', self::STATUTS_CONCLUS)->whereNotNull('buyer_id')
            ->selectRaw('buyer_id, COUNT(*) as n')->groupBy('buyer_id')->pluck('n');

        $actifs30 = Schema::hasTable('analytics_events')
            ? DB::table('analytics_events')->whereNotNull('user_id')->where('created_at', '>=', now()->subDays(30))->distinct()->count('user_id')
            : 0;
        $membres = User::count();

        $annonces12m = Listing::where('created_at', '>=', now()->subYear());

        return [
            'membres' => $membres,
            'membres_actifs_30j' => $actifs30,
            'taux_activite' => $membres > 0 ? round($actifs30 / $membres * 100) : 0,
            'vendeurs_actifs' => Listing::where('status', 'published')->distinct()->count('user_id'),
            'annonces_en_ligne' => Listing::where('status', 'published')->count(),
            'gmv_en_ligne' => round($gmv, 2),
            'revenus' => round($revenus, 2),
            'take_rate' => $gmv > 0 ? round($revenus / $gmv * 100, 1) : 0,
            'ventes_en_ligne' => $ventesEnLigne,
            'panier_moyen' => $ventesEnLigne > 0 ? round($gmv / $ventesEnLigne, 2) : 0,
            'acheteurs' => $acheteurs->count(),
            'acheteurs_recurrents' => $acheteurs->count() > 0 ? round($acheteurs->filter(fn ($n) => $n >= 2)->count() / $acheteurs->count() * 100) : 0,
            'taux_vente_annonces' => ($n = (clone $annonces12m)->count()) > 0
                ? round((clone $annonces12m)->where('status', 'sold')->count() / $n * 100) : 0,
            'delai_vente_jours' => self::delaiMoyenDeVente(),
            'installations_appli' => Schema::hasTable('app_installations') ? DB::table('app_installations')->count() : 0,
            'articles_reemployes' => Listing::where('status', 'sold')->count(),
        ];
    }

    /** Revenus Swap'Îles d'une requête de ventes (même règle que le tableau de bord). */
    private static function revenus($ventes): float
    {
        return (float) (clone $ventes)->sum('buyer_protection_fee') + (float) (clone $ventes)->sum('commission')
            + max(0.0, (float) (clone $ventes)->sum('relay_fee') - (float) (clone $ventes)->sum('relay_merchant_fee'));
    }

    /** Jours moyens entre la mise en ligne et la vente (toutes ventes conclues). */
    public static function delaiMoyenDeVente(): ?float
    {
        $delais = Transaction::query()
            ->whereIn('transactions.status', self::STATUTS_CONCLUS)
            ->join('listings', 'listings.id', '=', 'transactions.listing_id')
            ->get(['listings.created_at as publiee', 'transactions.created_at as vendue'])
            ->map(fn ($l) => max(0, Carbon::parse($l->publiee)->diffInHours(Carbon::parse($l->vendue)) / 24));

        return $delais->isEmpty() ? null : round($delais->avg(), 1);
    }

    /**
     * Mois par mois sur 12 mois : inscriptions, annonces, ventes, volume, revenus.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function parMois(int $mois = 12): array
    {
        $debut = now()->startOfMonth()->subMonths($mois - 1);
        $lignes = [];

        for ($i = 0; $i < $mois; $i++) {
            $de = $debut->copy()->addMonths($i);
            $a = $de->copy()->endOfMonth();
            $ventes = Transaction::query()->whereIn('status', TableauDeBord::STATUTS_VENDUS)->enLigne()->whereBetween('created_at', [$de, $a]);

            $lignes[] = [
                'mois' => $de->format('Y-m'),
                'libelle' => ucfirst($de->locale('fr')->translatedFormat('M Y')),
                'inscriptions' => User::whereBetween('created_at', [$de, $a])->count(),
                'annonces' => Listing::whereBetween('created_at', [$de, $a])->count(),
                'ventes_en_ligne' => (clone $ventes)->count(),
                'volume_en_ligne' => round((float) (clone $ventes)->sum('amount'), 2),
                'revenus' => round(self::revenus($ventes), 2),
                'ventes_main_propre' => Transaction::query()->where('payment_method', 'especes')->whereNull('acheteur_conteste_at')
                    ->whereIn('status', self::STATUTS_CONCLUS)->whereBetween('created_at', [$de, $a])->count(),
            ];
        }

        return $lignes;
    }

    // --- Marché ------------------------------------------------------------------

    /**
     * Par catégorie : offre (annonces en ligne, prix médian), demande
     * (favoris), ventes et délai. Catégories trop petites masquées.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function parCategorie(): array
    {
        $annonces = Listing::query()->where('status', 'published')->whereNotNull('category_level1')
            ->get(['id', 'category_level1', 'price']);

        $favoris = DB::table('favorites')->join('listings', 'listings.id', '=', 'favorites.listing_id')
            ->whereNotNull('listings.category_level1')
            ->selectRaw('listings.category_level1 as cat, COUNT(*) as n')->groupBy('cat')->pluck('n', 'cat');

        $ventes = Transaction::query()->whereIn('transactions.status', self::STATUTS_CONCLUS)
            ->join('listings', 'listings.id', '=', 'transactions.listing_id')
            ->selectRaw('listings.category_level1 as cat, COUNT(*) as n, AVG(transactions.seller_amount) as prix')
            ->groupBy('cat')->get()->keyBy('cat');

        return $annonces->groupBy(fn ($l) => mb_strtolower((string) $l->category_level1))
            ->map(function (Collection $groupe, string $cat) use ($favoris, $ventes) {
                $vente = $ventes[$cat] ?? $ventes[ucfirst($cat)] ?? null;

                return [
                    'categorie' => Categories::label($cat) ?? ucfirst($cat),
                    'annonces' => $groupe->count(),
                    'prix_median' => self::mediane($groupe->pluck('price')->map(fn ($p) => (float) $p)->filter(fn ($p) => $p > 0)->all()),
                    'favoris' => (int) ($favoris[$cat] ?? $favoris[ucfirst($cat)] ?? 0),
                    'ventes' => (int) ($vente->n ?? 0),
                    'prix_vente_moyen' => $vente && $vente->n >= self::SEUIL_ANONYMAT ? round((float) $vente->prix, 2) : null,
                ];
            })
            ->filter(fn ($l) => $l['annonces'] >= self::SEUIL_ANONYMAT)
            ->sortByDesc('annonces')->values()->all();
    }

    /** @return array<int,array<string,mixed>> */
    public static function parIle(): array
    {
        return collect(TerritoireContext::LABELS)->map(function (string $ile) {
            $ventes = Transaction::query()->whereIn('transactions.status', self::STATUTS_CONCLUS)
                ->join('listings', 'listings.id', '=', 'transactions.listing_id')
                ->where('listings.territoire', $ile);
            $nbVentes = (clone $ventes)->count();

            return [
                'ile' => $ile,
                'membres' => User::where('territoire', $ile)->count(),
                'annonces_en_ligne' => Listing::where('status', 'published')->where('territoire', $ile)->count(),
                'ventes' => $nbVentes,
                'panier_moyen' => $nbVentes >= self::SEUIL_ANONYMAT ? round((float) (clone $ventes)->avg('transactions.amount'), 2) : null,
            ];
        })->filter(fn ($l) => $l['membres'] >= self::SEUIL_ANONYMAT)->values()->all();
    }

    /** Marques les plus présentes (au moins SEUIL_ANONYMAT annonces). */
    public static function marques(int $limite = 15): array
    {
        return Listing::query()->where('status', 'published')->whereNotNull('marque')->where('marque', '!=', '')
            ->get(['marque', 'price'])
            ->groupBy(fn ($l) => mb_strtolower(trim($l->marque)))
            ->reject(fn ($g, $m) => in_array($m, ['autre', 'aucune', 'sans marque', 'non', '-', '—'], true))
            ->map(fn (Collection $g) => [
                'marque' => trim($g->first()->marque),
                'annonces' => $g->count(),
                'prix_median' => self::mediane($g->pluck('price')->map(fn ($p) => (float) $p)->filter(fn ($p) => $p > 0)->all()),
            ])
            ->filter(fn ($l) => $l['annonces'] >= self::SEUIL_ANONYMAT)
            ->sortByDesc('annonces')->take($limite)->values()->all();
    }

    /**
     * Ce que les gens cherchent sans le trouver : la demande non satisfaite.
     * Seuls les termes cherchés par au moins SEUIL_ANONYMAT personnes ou
     * visiteurs différents, sur 90 jours.
     */
    public static function demandeNonSatisfaite(int $limite = 20): array
    {
        if (! Schema::hasTable('search_no_results')) {
            return [];
        }

        $colonneVisiteur = Schema::hasColumn('search_no_results', 'visitor_id') ? 'COALESCE(visitor_id, user_id)' : 'user_id';

        return DB::table('search_no_results')
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw("term, COUNT(*) as recherches, COUNT(DISTINCT {$colonneVisiteur}) as personnes")
            ->groupBy('term')
            ->having('personnes', '>=', self::SEUIL_ANONYMAT)
            ->orderByDesc('recherches')
            ->limit($limite)
            ->get()
            ->map(fn ($l) => ['terme' => $l->term, 'recherches' => (int) $l->recherches, 'personnes' => (int) $l->personnes])
            ->all();
    }

    /** Comment les articles changent de main. */
    public static function modesDeVente(): array
    {
        $libelles = [
            'colissimo' => 'Colissimo',
            'relay' => 'Point relais',
            'hand_delivery' => 'Main propre',
        ];

        $total = 0;
        $parMode = Transaction::query()->whereIn('status', self::STATUTS_CONCLUS)
            ->selectRaw('delivery_method, payment_method, COUNT(*) as n')->groupBy('delivery_method', 'payment_method')->get()
            ->reduce(function ($acc, $l) use (&$total, $libelles) {
                $cle = match ($l->payment_method) {
                    'echange' => 'Échange',
                    'don' => 'Don',
                    'especes' => 'Main propre (espèces)',
                    default => ($libelles[$l->delivery_method] ?? 'Autre') . ' (paiement en ligne)',
                };
                $acc[$cle] = ($acc[$cle] ?? 0) + (int) $l->n;
                $total += (int) $l->n;

                return $acc;
            }, []);

        arsort($parMode);

        return collect($parMode)->map(fn ($n, $mode) => ['mode' => $mode, 'ventes' => $n, 'part' => $total ? round($n / $total * 100) : 0])->values()->all();
    }

    /** Jours de la semaine où l'on vend le plus. */
    public static function ventesParJour(): array
    {
        $jours = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
        $compte = array_fill_keys(array_keys($jours), 0);

        Transaction::query()->whereIn('status', self::STATUTS_CONCLUS)->pluck('created_at')
            ->each(function ($d) use (&$compte) {
                $compte[Carbon::parse($d)->dayOfWeekIso]++;
            });

        return collect($jours)->map(fn ($nom, $n) => ['jour' => $nom, 'ventes' => $compte[$n]])->values()->all();
    }

    // --- Outils ----------------------------------------------------------------

    public static function mediane(array $valeurs): ?float
    {
        $valeurs = array_values($valeurs);
        sort($valeurs);
        $n = count($valeurs);
        if ($n === 0) {
            return null;
        }

        return round($n % 2 ? $valeurs[intdiv($n, 2)] : ($valeurs[$n / 2 - 1] + $valeurs[$n / 2]) / 2, 2);
    }

    /**
     * Export CSV (point-virgule, pour Excel en français) : uniquement les
     * tableaux agrégés ci-dessus.
     */
    public static function csv(): string
    {
        $blocs = [
            'Croissance mois par mois' => self::parMois(),
            'Par catégorie' => self::parCategorie(),
            'Par île' => self::parIle(),
            'Marques' => self::marques(),
            'Recherches sans résultat (demande non satisfaite)' => self::demandeNonSatisfaite(),
            'Modes de vente' => self::modesDeVente(),
            'Ventes par jour de la semaine' => self::ventesParJour(),
        ];

        $f = fopen('php://temp', 'r+');
        fwrite($f, "\xEF\xBB\xBF"); // BOM : accents corrects dans Excel
        fputcsv($f, ["Swap'Îles — données agrégées et anonymes — " . now()->format('d/m/Y')], ';');

        foreach (self::croissance() as $cle => $valeur) {
            fputcsv($f, [$cle, $valeur], ';');
        }

        foreach ($blocs as $titre => $lignes) {
            fputcsv($f, [], ';');
            fputcsv($f, [$titre], ';');
            if ($lignes) {
                fputcsv($f, array_keys($lignes[0]), ';');
                foreach ($lignes as $l) {
                    fputcsv($f, array_map(fn ($v) => is_float($v) ? str_replace('.', ',', (string) $v) : $v, array_values($l)), ';');
                }
            }
        }

        rewind($f);

        return stream_get_contents($f);
    }
}
