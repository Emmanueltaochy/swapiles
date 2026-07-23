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

        // Priorité : l'île du membre connecté > le cookie > La Réunion par défaut.
        $authTerritoire = $request->user()?->territoire;
        $cookieTerritoire = $request->cookie('swapiles_territoire');

        if ($authTerritoire && in_array($authTerritoire, $validLabels, true)) {
            $selectedTerritoire = $authTerritoire;
            $hasSelectedTerritoire = true;
        } elseif ($cookieTerritoire && in_array($cookieTerritoire, $validLabels, true)) {
            $selectedTerritoire = $cookieTerritoire;
            $hasSelectedTerritoire = true;
        } else {
            $selectedTerritoire = 'La Réunion';
            $hasSelectedTerritoire = false;
        }

        $selectedKey = collect($this->territoires)
            ->search(fn ($item) => $item['label'] === $selectedTerritoire);

        $selectedMeta = $this->territoires[$selectedKey ?: 'reunion'];

        $listings = Listing::query()
            ->with(['images', 'user'])->withCount('favoritedBy')
            ->where('status', 'published')
            ->where('territoire', $selectedTerritoire)
            ->latest()
            ->paginate(24);

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
            ->where('territoire', $selectedTerritoire)
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
            $query->where('requires_online_payment', true)
                  ->where('allows_colissimo', true);
        }

        $selectedTerritoire = $request->filled('territoire')
            ? $request->territoire
            : $request->cookie('swapiles_territoire', 'La Réunion');

        if ($selectedTerritoire) {
            $query->where('territoire', $selectedTerritoire);
        }

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
            default => $query->latest(),
        };

        $listings = $query->paginate(48)->withQueryString();

        return view('search', compact('listings', 'selectedTerritoire', 'categoryTree'));
    }
}
