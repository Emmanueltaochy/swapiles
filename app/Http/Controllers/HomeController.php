<?php

namespace App\Http\Controllers;

use App\Models\Listing;
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
        $validLabels = array_column($this->territoires, 'label');

        // Priorité : le choix explicite (cookie, mis à jour à chaque changement
        // de territoire) > l'île du profil par défaut > La Réunion.
        // L'inscription enregistre déjà le cookie = île du profil, donc le défaut
        // reste l'île du membre, tout en laissant le changement de territoire agir.
        $cookieTerritoire = $request->cookie('swapiles_territoire');
        $authTerritoire = $request->user()?->territoire;

        if ($cookieTerritoire && in_array($cookieTerritoire, $validLabels, true)) {
            $selectedTerritoire = $cookieTerritoire;
            $hasSelectedTerritoire = true;
        } elseif ($authTerritoire && in_array($authTerritoire, $validLabels, true)) {
            $selectedTerritoire = $authTerritoire;
            $hasSelectedTerritoire = true;
        } else {
            $selectedTerritoire = 'La Réunion';
            $hasSelectedTerritoire = false;
        }

        $selectedKey = collect($this->territoires)
            ->search(fn ($item) => $item['label'] === $selectedTerritoire);

        $selectedMeta = $this->territoires[$selectedKey ?: 'reunion'];

        // Une annonce apparaît sur son île principale ET sur ses îles
        // supplémentaires (vendeurs ayant activé Colissimo pour l'inter-îles).
        $alsoColExists = \Illuminate\Support\Facades\Schema::hasColumn('listings', 'also_territoires');
        $territoireFilter = function ($q) use ($selectedTerritoire, $alsoColExists) {
            $q->where('territoire', $selectedTerritoire);
            if ($alsoColExists) {
                $q->orWhereRaw('JSON_CONTAINS(also_territoires, ?)', ['"' . $selectedTerritoire . '"']);
            }
        };

        // Feed : on montre TOUTES les annonces (toutes îles), mais on remonte
        // d'abord celles achetables depuis l'île choisie (locale en main propre
        // OU expédiables Colissimo), puis les autres îles (à faire expédier).
        $listingsQuery = Listing::query()
            ->with(['images', 'user'])->withCount('favoritedBy')
            ->where('status', 'published');
        $this->applyBuyableOrdering($listingsQuery, $selectedTerritoire, $alsoColExists);
        $listings = $listingsQuery->paginate(24);

        $territoryCounts = Listing::query()
            ->where('status', 'published')
            ->selectRaw('territoire, COUNT(*) as total')
            ->groupBy('territoire')
            ->pluck('total', 'territoire');

        $activeListingsCount = (int) ($territoryCounts[$selectedTerritoire] ?? 0);
        $totalListingsCount = (int) $territoryCounts->sum();

        $crossIslandAvailableCount = Listing::query()
            ->where('status', 'published')
            ->where('territoire', '!=', $selectedTerritoire)
            ->where('requires_online_payment', true)
            ->where('allows_colissimo', true)
            ->count();

        $globalShippableListings = Listing::query()
            ->with(['images', 'user'])->withCount('favoritedBy')
            ->where('status', 'published')
            ->where('requires_online_payment', true)
            ->where('allows_colissimo', true)
            ->latest()
            ->take(12)
            ->get();

        $popularListings = Listing::query()
            ->with(['images', 'user'])->withCount('favoritedBy')
            ->where('status', 'published')
            ->where($territoireFilter)
            ->orderByDesc('views_count')
            ->latest()
            ->take(8)
            ->get();

        $securePaymentListingsCount = Listing::query()
            ->where('status', 'published')
            ->where('requires_online_payment', true)
            ->count();

        $membersCount = \App\Models\User::count();

        $todayListingsCount = Listing::query()
            ->where('status', 'published')
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $otherIslandListings = [];

        foreach ($this->territoires as $key => $meta) {
            if ($meta['label'] === $selectedTerritoire) {
                continue;
            }

            $otherIslandListings[$key] = [
                'label' => $meta['label'],
                'flag' => $meta['flag'],
                'count' => (int) ($territoryCounts[$meta['label']] ?? 0),
                'listings' => Listing::query()
                    ->with(['images', 'user'])->withCount('favoritedBy')
                    ->where('status', 'published')
                    ->where('territoire', $meta['label'])
                    ->where('requires_online_payment', true)
                    ->where('allows_colissimo', true)
                    ->latest()
                    ->take(4)
                    ->get(),
            ];
        }

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
        if (! $territoire) {
            $query->latest();

            return;
        }

        $sql = 'CASE WHEN (territoire = ? ';
        $bindings = [$territoire];

        if ($alsoColExists) {
            $sql .= 'OR JSON_CONTAINS(also_territoires, ?) ';
            $bindings[] = '"' . $territoire . '"';
        }

        $sql .= 'OR (requires_online_payment = 1 AND allows_colissimo = 1)) THEN 0 ELSE 1 END';

        $query->orderByRaw($sql, $bindings)->latest();
    }

    public function search(Request $request)
    {
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
            ->with(['images', 'user'])->withCount('favoritedBy')
            ->where('status', 'published');

        if ($request->filled('q')) {
            $search = trim($request->q);

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhere('marque', 'like', '%' . $search . '%')
                  ->orWhere('category_level1', 'like', '%' . $search . '%')
                  ->orWhere('category_level2', 'like', '%' . $search . '%')
                  ->orWhere('category_level3', 'like', '%' . $search . '%');
            });
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

        if ($request->boolean('inter_iles')) {
            $query->onlinePayable()
                  ->where('allows_colissimo', true);
        }

        // Facette PAIEMENT (choix multiple, OR entre les options cochées).
        $payments = array_values(array_filter((array) $request->input('payment', [])));
        if (! empty($payments)) {
            $query->where(function ($outer) use ($payments) {
                foreach ($payments as $payment) {
                    match ($payment) {
                        // Carte : réellement payable (vendeur Stripe opérationnel).
                        'cb' => $outer->orWhere(fn ($q) => $q->where('requires_online_payment', true)
                            ->whereHas('user', fn ($u) => $u->whereNotNull('stripe_account_id')
                                ->where('stripe_charges_enabled', true)
                                ->where('stripe_payouts_enabled', true))),
                        // Espèces / remise en main propre (vente avec prix).
                        'cash' => $outer->orWhere(fn ($q) => $q->where('allows_hand_delivery', true)
                            ->where('price', '>', 0)
                            ->whereIn('listing_type', ['achat', 'negoce-prix'])),
                        'exchange' => $outer->orWhere(fn ($q) => $q->where('allows_exchange', true)
                            ->orWhere('listing_type', 'echange-produits')),
                        'don' => $outer->orWhere(fn ($q) => $q->where('listing_type', 'don')
                            ->orWhere('price', '<=', 0)),
                        default => null,
                    };
                }
            });
        }

        // Si le paramètre territoire est présent (même vide = « Tous les
        // territoires »), on respecte ce choix explicite. Sinon, on retombe sur
        // le cookie / défaut.
        if ($request->has('territoire')) {
            $selectedTerritoire = trim((string) $request->territoire); // '' = tous
        } else {
            $selectedTerritoire = $request->cookie('swapiles_territoire', 'La Réunion');
        }

        // On NE filtre PLUS par territoire : les annonces des autres îles restent
        // visibles (même non expédiables) pour que l'acheteur puisse signaler son
        // intérêt et pousser le vendeur à activer Colissimo. Le territoire choisi
        // sert uniquement à remonter en premier les annonces achetables (voir plus bas).
        $alsoColExists = \Illuminate\Support\Facades\Schema::hasColumn('listings', 'also_territoires');

        if ($request->filled('etat')) {
            $query->where('etat', $request->etat);
        }

        if ($request->filled('taille')) {
            $query->where('taille', $request->taille);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (int) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (int) $request->max_price);
        }

        match ($request->get('sort')) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'oldest' => $query->oldest(),
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
                        $q->orWhereRaw('JSON_CONTAINS(also_territoires, ?)', ['"' . $selectedTerritoire . '"']);
                    }
                })
                ->count();
        }

        $listings = $query->paginate(48)->withQueryString();

        return view('search', compact('listings', 'selectedTerritoire', 'categoryTree', 'localCount'));
    }
}
