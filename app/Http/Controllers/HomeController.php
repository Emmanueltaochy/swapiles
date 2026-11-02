<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Support\TerritoireContext;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    private array $territoires = [
        'reunion' => ['label' => 'La Réunion', 'flag' => '🇷🇪'],
        'guyane' => ['label' => 'Guyane', 'flag' => '🇬🇫'],
        'martinique' => ['label' => 'Martinique', 'flag' => '🇲🇶'],
        'guadeloupe' => ['label' => 'Guadeloupe', 'flag' => '🇬🇵'],
        'mayotte' => ['label' => 'Mayotte', 'flag' => '🇾🇹'],
    ];

    public function index(Request $request)
    {
        // Une seule règle pour tout le site (accueil, recherche, compteur de
        // visites) : voir App\Support\TerritoireContext.
        $selectedTerritoire = TerritoireContext::resolve($request);
        $hasSelectedTerritoire = TerritoireContext::isKnown($request);

        $selectedKey = collect($this->territoires)
            ->search(fn ($item) => $item['label'] === $selectedTerritoire);

        $selectedMeta = $this->territoires[$selectedKey ?: 'reunion'];

        // Une annonce apparaît sur son île principale ET sur ses îles
        // supplémentaires (vendeurs ayant activé Colissimo pour l'inter-îles).
        $alsoColExists = \Illuminate\Support\Facades\Schema::hasColumn('listings', 'also_territoires');
        $territoireFilter = function ($q) use ($selectedTerritoire, $alsoColExists) {
            $q->where('territoire', $selectedTerritoire);
            if ($alsoColExists) {
                $q->orWhere('also_territoires', 'like', '%"' . $selectedTerritoire . '"%');
            }
        };

        // Feed : on montre TOUTES les annonces (toutes îles), mais on remonte
        // d'abord celles achetables depuis l'île choisie (locale en main propre
        // OU expédiables Colissimo), puis les autres îles (à faire expédier).
        $listingsQuery = Listing::query()
            ->with(['images', 'user'])->withCount(['favorisRecus as favorited_by_count'])
            ->where('status', 'published');
        $this->applyBuyableOrdering($listingsQuery, $selectedTerritoire, $alsoColExists);
        $listings = $listingsQuery->paginate(24);

        // Chiffres et sélections identiques pour tous les visiteurs d'une île :
        // calculés une fois par minute au lieu d'à chaque visite (afflux d'un live,
        // d'une pub…). Le fil d'annonces et les sélections personnelles restent en direct.
        [
            'territoryCounts' => $territoryCounts, 'activeListingsCount' => $activeListingsCount, 'totalListingsCount' => $totalListingsCount,
            'crossIslandAvailableCount' => $crossIslandAvailableCount, 'globalShippableListings' => $globalShippableListings,
            'populairesToujours' => $populairesToujours, 'securePaymentListingsCount' => $securePaymentListingsCount,
            'membersCount' => $membersCount, 'todayListingsCount' => $todayListingsCount, 'otherIslandListings' => $otherIslandListings,
        ] = $this->accueilCommun($selectedTerritoire);

        // Populaires CES JOURS-CI, et une sélection qui tourne (voir Tendances).
        $popularListings = \App\Support\Tendances::populaires(
            $territoireFilter,
            $request->user(),
            \App\Support\Tendances::graine((string) ($request->user()?->id ?? $request->session()->getId())),
        );

        // Stories automatiques : un rond par dressing qui a publié récemment.
        $stories = \App\Support\Stories::pour($request->user(), $selectedTerritoire);

        return view('home', [
            'listings' => $listings,
            'activeListingsCount' => $activeListingsCount,
            'totalListingsCount' => $totalListingsCount,
            'territoryCounts' => $territoryCounts,
            'territoires' => $this->territoires,
            'selectedTerritoire' => $selectedTerritoire,
            'selectedMeta' => $selectedMeta,
            'hasSelectedTerritoire' => $hasSelectedTerritoire,
            'otherIslandListings' => $otherIslandListings,
            'crossIslandAvailableCount' => $crossIslandAvailableCount,
            'globalShippableListings' => $globalShippableListings,
            'popularListings' => $popularListings,
            'populairesToujours' => $populairesToujours,
            'stories' => $stories,
            'securePaymentListingsCount' => $securePaymentListingsCount,
            'membersCount' => $membersCount,
            'todayListingsCount' => $todayListingsCount,
        ]);
    }

    /**
     * Trie une requête d'annonces pour remonter d'abord celles achetables depuis
     * l'île sélectionnée (locale = remise en main propre, OU expédiable Colissimo),
     * puis les autres (autres îles non expédiables, à demander au vendeur).
     */
    private function applyBuyableOrdering($query, ?string $territoire, bool $alsoColExists): void
    {
        // « Plus récentes » = publiées ou remontées récemment (voir Remontees).
        if (! $territoire) {
            \App\Support\Remontees::trier($query);

            return;
        }

        $sql = 'CASE WHEN (territoire = ? ';
        $bindings = [$territoire];

        if ($alsoColExists) {
            $sql .= 'OR also_territoires LIKE ? ';
            $bindings[] = '%"' . $territoire . '"%';
        }

        $sql .= 'OR (requires_online_payment = 1 AND allows_colissimo = 1)) THEN 0 ELSE 1 END';

        \App\Support\Remontees::trier($query->orderByRaw($sql, $bindings));
    }

    /**
     * Partie de l'accueil identique pour tous les visiteurs d'une île. En cache
     * une minute, mais seulement des nombres et des identifiants d'annonces (le
     * cache n'accepte pas d'objets, par sécurité) : les annonces sont ensuite
     * rechargées en une seule requête.
     *
     * @return array<string,mixed>
     */
    private function accueilCommun(string $selectedTerritoire): array
    {
        $calcul = function () use ($selectedTerritoire) {
            $territoryCounts = Listing::query()->where('status', 'published')
                ->selectRaw('territoire, COUNT(*) as total')->groupBy('territoire')
                ->pluck('total', 'territoire')->map(fn ($n) => (int) $n)->all();

            $expediables = fn () => Listing::query()->where('status', 'published')
                ->where('requires_online_payment', true)->where('allows_colissimo', true);

            $autresIles = [];
            foreach ($this->territoires as $key => $meta) {
                if ($meta['label'] !== $selectedTerritoire) {
                    $autresIles[$key] = \App\Support\Remontees::trier($expediables()->where('territoire', $meta['label']))->take(4)->pluck('id')->all();
                }
            }

            return [
                'territoryCounts' => $territoryCounts,
                'crossIslandAvailableCount' => $expediables()->where('territoire', '!=', $selectedTerritoire)->count(),
                'globalShippable' => \App\Support\Remontees::trier($expediables())->take(12)->pluck('id')->all(),
                'populairesToujours' => \App\Support\Tendances::depuisToujours()->pluck('id')->all(),
                'securePaymentListingsCount' => Listing::query()->where('status', 'published')->where('requires_online_payment', true)->count(),
                'membersCount' => \App\Models\User::count(),
                'todayListingsCount' => Listing::query()->where('status', 'published')->whereDate('created_at', now()->toDateString())->count(),
                'autresIles' => $autresIles,
            ];
        };

        $c = app()->runningUnitTests()
            ? $calcul()
            : \Illuminate\Support\Facades\Cache::remember('accueil:commun:' . md5($selectedTerritoire), 60, $calcul);

        // Toutes les annonces des sélections en une seule requête, dans l'ordre d'origine.
        $ids = array_merge($c['globalShippable'], $c['populairesToujours'], ...array_values($c['autresIles']));
        $annonces = $ids === [] ? collect() : Listing::query()
            ->with(['images', 'user'])->withCount(['favorisRecus as favorited_by_count'])
            ->whereIn('id', array_unique($ids))->where('status', 'published')
            ->get()->keyBy('id');
        $dans = fn (array $liste) => collect($liste)->map(fn ($id) => $annonces->get($id))->filter()->values();

        $territoryCounts = collect($c['territoryCounts']);
        $otherIslandListings = [];
        foreach ($c['autresIles'] as $key => $liste) {
            $meta = $this->territoires[$key];
            $otherIslandListings[$key] = [
                'label' => $meta['label'],
                'flag' => $meta['flag'],
                'count' => (int) ($territoryCounts[$meta['label']] ?? 0),
                'listings' => $dans($liste),
            ];
        }

        return [
            'territoryCounts' => $territoryCounts,
            'activeListingsCount' => (int) ($territoryCounts[$selectedTerritoire] ?? 0),
            'totalListingsCount' => (int) $territoryCounts->sum(),
            'crossIslandAvailableCount' => $c['crossIslandAvailableCount'],
            'globalShippableListings' => $dans($c['globalShippable']),
            'populairesToujours' => $dans($c['populairesToujours']),
            'securePaymentListingsCount' => $c['securePaymentListingsCount'],
            'membersCount' => $c['membersCount'],
            'todayListingsCount' => $c['todayListingsCount'],
            'otherIslandListings' => $otherIslandListings,
        ];
    }

    public function search(Request $request)
    {
        // « femme », « high-tech »… tapé ou reçu dans un lien : c'est une
        // catégorie, pas une recherche de texte (aucun titre ne contient
        // « femme », d'où « Aucune annonce trouvée »). D'anciens liens connus
        // de Google pointaient ainsi vers une recherche vide : on les envoie
        // vers la bonne catégorie, définitivement (301).
        if ($request->filled('q') && ! $request->filled('category')) {
            $categorie = \App\Support\Categories::niveau1DepuisTexte($request->input('q'));

            if ($categorie) {
                return redirect()->route('search', array_merge(
                    $request->except(['q', 'page']),
                    ['category' => $categorie]
                ), 301);
            }
        }

        $categoryRows = Listing::query()
            ->where('status', 'published')
            ->whereNotNull('category_level1')
            ->select('category_level1', 'category_level2', 'category_level3')
            ->distinct()
            ->orderBy('category_level1')
            ->orderBy('category_level2')
            ->orderBy('category_level3')
            ->get();

        $categoryTree = [];

        foreach ($categoryRows as $row) {
            $level1 = $row->category_level1;
            $level2 = $row->category_level2;
            $level3 = $row->category_level3;

            if (!isset($categoryTree[$level1])) {
                $categoryTree[$level1] = [];
            }

            if ($level2 && !isset($categoryTree[$level1][$level2])) {
                $categoryTree[$level1][$level2] = [];
            }

            if ($level2 && $level3 && !in_array($level3, $categoryTree[$level1][$level2])) {
                $categoryTree[$level1][$level2][] = $level3;
            }
        }

        $query = Listing::query()
            ->with(['images', 'user'])->withCount(['favorisRecus as favorited_by_count'])
            ->where('status', 'published');

        if ($request->filled('q')) {
            // Mot par mot : « jardin plante » trouve « Plante verte » rangée dans Jardin.
            \App\Support\RechercheTexte::appliquer($query, (string) $request->q);
        }

        if ($request->filled('category')) {
            $category = strtolower($request->category);

            $query->where(function ($q) use ($category) {
                $q->where('category_level1', $category)
                  ->orWhere('category_level2', $category)
                  ->orWhere('category_level3', $category);
            });
        }

        if ($request->filled('category_level2')) {
            if ($request->filled('category')) {
                $query->where('category_level1', strtolower($request->category));
            }

            $query->where('category_level2', $request->category_level2);
        }

        if ($request->filled('category_level3')) {
            if ($request->filled('category')) {
                $query->where('category_level1', strtolower($request->category));
            }

            if ($request->filled('category_level2')) {
                $query->where('category_level2', $request->category_level2);
            }

            $query->where('category_level3', $request->category_level3);
        }

        if ($request->filled('listing_type')) {
            $query->where('listing_type', $request->listing_type);
        }

        // Facette PAIEMENT (choix multiple, OR entre les options cochées).
        // On applique chaque condition via une sous-requête booléenne compilée
        // (whereIn sur des ids) pour éviter tout souci d'imbrication/bindings
        // avec le tri (orderByRaw) sur MySQL.
        $payments = array_values(array_filter((array) $request->input('payment', [])));
        if (! empty($payments)) {
            $matchIds = collect();

            foreach ($payments as $payment) {
                $sub = Listing::query()->where('status', 'published')->select('id');

                match ($payment) {
                    'cb' => $sub->onlinePayable(),
                    'cash' => $sub->where('allows_hand_delivery', true)
                        ->where('price', '>', 0)
                        ->whereIn('listing_type', ['achat', 'negoce-prix']),
                    'exchange' => $sub->where(fn ($q) => $q->where('allows_exchange', true)
                        ->orWhere('listing_type', 'echange-produits')),
                    'don' => $sub->where(fn ($q) => $q->where('listing_type', 'don')
                        ->orWhere('price', '<=', 0)),
                    default => $sub->whereRaw('1 = 0'),
                };

                $matchIds = $matchIds->merge($sub->pluck('id'));
            }

            $query->whereIn('id', $matchIds->unique()->values());
        }

        // Si le paramètre territoire est présent (même vide = « Tous les
        // territoires »), on respecte ce choix explicite. Sinon, on retombe sur
        // le cookie / défaut.
        if ($request->has('territoire')) {
            $selectedTerritoire = trim((string) $request->territoire); // '' = tous
        } else {
            $selectedTerritoire = TerritoireContext::resolve($request);
        }

        $alsoColExists = \Illuminate\Support\Facades\Schema::hasColumn('listings', 'also_territoires');
        $hasTerritoire = ($selectedTerritoire !== '' && $selectedTerritoire !== null);
        $interIles = $request->boolean('inter_iles');
        $wantsCash = in_array('cash', $payments, true);

        // Filtre TERRITOIRE — règles strictes (aucune exception) :
        //  • « Inter-îles » DÉCOCHÉ  → uniquement le territoire choisi (pas de
        //    cross-île), quel que soit le mode de paiement.
        //  • Mode « Espèces / main propre » → uniquement le territoire choisi :
        //    une remise en main propre entre deux îles est physiquement impossible.
        //  • Sinon (inter-îles coché, sans main propre) → territoire choisi OU
        //    annonces expédiables depuis une autre île (Colissimo / also_territoires).
        if ($hasTerritoire) {
            $selLower = mb_strtolower($selectedTerritoire);

            if (! $interIles || $wantsCash) {
                $query->whereRaw('LOWER(TRIM(territoire)) = ?', [$selLower]);
            } else {
                $query->where(function ($q) use ($selLower, $selectedTerritoire, $alsoColExists) {
                    $q->whereRaw('LOWER(TRIM(territoire)) = ?', [$selLower])
                      ->orWhere(function ($q2) {
                          $q2->where('requires_online_payment', true)
                             ->where('allows_colissimo', true);
                      });
                    if ($alsoColExists) {
                        $q->orWhere('also_territoires', 'like', '%"' . $selectedTerritoire . '"%');
                    }
                });
            }
        }

        if ($request->filled('etat')) {
            // On matche toutes les variantes du même état (« Très bon état »,
            // « Tres-bon-etat »…) pour ne pas rater les annonces importées.
            $target = \App\Support\Etat::normalize($request->etat);

            $variants = Listing::query()
                ->where('status', 'published')
                ->whereNotNull('etat')
                ->distinct()
                ->pluck('etat')
                ->filter(fn ($e) => \App\Support\Etat::normalize($e) === $target)
                ->values()
                ->all();

            if (! empty($variants)) {
                $query->whereIn('etat', $variants);
            } else {
                $query->where('etat', $request->etat);
            }
        }

        if ($request->filled('taille')) {
            // Insensible à la casse / aux espaces (S = s = « S »).
            $taille = trim((string) $request->taille);
            $query->whereRaw('LOWER(TRIM(taille)) = ?', [mb_strtolower($taille)]);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (int) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (int) $request->max_price);
        }

        match ($request->get('sort')) {
            // Départage par identifiant : un ordre stable d'une page à l'autre.
            'price_asc' => $query->orderBy('price', 'asc')->orderByDesc('listings.id'),
            'price_desc' => $query->orderBy('price', 'desc')->orderByDesc('listings.id'),
            'oldest' => $query->oldest()->orderBy('listings.id'),
            // Tri par défaut : achetables depuis l'île choisie d'abord, puis le reste.
            default => $this->applyBuyableOrdering(
                $query,
                ($selectedTerritoire !== '' && $selectedTerritoire !== null) ? $selectedTerritoire : null,
                $alsoColExists
            ),
        };

        // Y a-t-il au moins une annonce « autour de vous » (île sélectionnée) dans
        // les résultats ? Sinon on affiche un message avant les annonces des autres îles.
        $localCount = 0;
        if ($selectedTerritoire !== '' && $selectedTerritoire !== null) {
            $localCount = (clone $query)
                ->where(function ($q) use ($selectedTerritoire, $alsoColExists) {
                    $q->where('territoire', $selectedTerritoire);
                    if ($alsoColExists) {
                        $q->orWhere('also_territoires', 'like', '%"' . $selectedTerritoire . '"%');
                    }
                })
                ->count();
        }

        $listings = $query->paginate(48)->withQueryString();

        // Point 17 : journaliser une recherche textuelle SANS résultat (hors bots),
        // pour révéler la demande hors catalogue. Le terme est aussi passé à la
        // vue pour proposer une alerte e-mail.
        // Une recherche = sa première page (pas la suite chargée au défilement).
        $premierePage = (int) $request->input('page', 1) <= 1 && ! $request->hasHeader('X-Swp-Infini');
        $noResultTerm = $premierePage ? \App\Support\SearchLogger::record(
            (string) $request->input('q', ''),
            $listings->total(),
            optional($request->user())->id,
            (string) $request->userAgent(),
            $request->attributes->get('swp_vid') ?? $request->cookie('swp_vid'),
            ($selectedTerritoire ?? '') ?: null
        ) : null;

        return view('search', compact('listings', 'selectedTerritoire', 'categoryTree', 'localCount', 'noResultTerm'));
    }
}
