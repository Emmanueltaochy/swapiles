<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pages de destination SEO par territoire et par catégorie.
 *
 * Inspiré de la force de Vinted : un large catalogue de pages indexables,
 * ciblées sur des requêtes réelles (« vêtements d'occasion à La Réunion »),
 * qui concentrent le jus SEO et redirigent vers les annonces.
 */
class CatalogController extends Controller
{
    /** Slug d'URL => libellé de territoire stocké en base. */
    private array $territoires = [
        'la-reunion' => 'La Réunion',
        'martinique' => 'Martinique',
        'guadeloupe' => 'Guadeloupe',
        'guyane' => 'Guyane',
        'mayotte' => 'Mayotte',
    ];

    private array $territoireFlags = [
        'La Réunion' => '🇷🇪',
        'Martinique' => '🇲🇶',
        'Guadeloupe' => '🇬🇵',
        'Guyane' => '🇬🇫',
        'Mayotte' => '🇾🇹',
    ];

    /**
     * Page d'un territoire : /iles/{territoire}
     */
    public function territoire(Request $request, string $territoire)
    {
        $label = $this->territoires[$territoire] ?? null;
        abort_if($label === null, 404);

        $listings = Listing::query()
            ->with(['images', 'user'])
            ->where('status', 'published')
            ->where('territoire', $label)
            ->latest()
            ->paginate(24);

        $categories = $this->categoriesFor($label);
        $totalCount = Listing::where('status', 'published')->where('territoire', $label)->count();

        return view('catalog.territoire', [
            'territoireSlug' => $territoire,
            'territoireLabel' => $label,
            'territoireFlag' => $this->territoireFlags[$label] ?? '🏝️',
            'listings' => $listings,
            'categories' => $categories,
            'totalCount' => $totalCount,
            'allTerritoires' => $this->territoires,
        ]);
    }

    /**
     * Page catégorie dans un territoire : /iles/{territoire}/{categorie}
     */
    public function category(Request $request, string $territoire, string $categorie)
    {
        $label = $this->territoires[$territoire] ?? null;
        abort_if($label === null, 404);

        // Résolution du slug de catégorie vers la valeur category_level1 réelle.
        $categoryLabel = $this->resolveCategory($label, $categorie);
        abort_if($categoryLabel === null, 404);

        $listings = Listing::query()
            ->with(['images', 'user'])
            ->where('status', 'published')
            ->where('territoire', $label)
            ->where('category_level1', $categoryLabel)
            ->latest()
            ->paginate(24);

        $categories = $this->categoriesFor($label);
        $totalCount = $listings->total();

        return view('catalog.category', [
            'territoireSlug' => $territoire,
            'territoireLabel' => $label,
            'territoireFlag' => $this->territoireFlags[$label] ?? '🏝️',
            'categoryLabel' => $categoryLabel,
            'categorySlug' => $categorie,
            'listings' => $listings,
            'categories' => $categories,
            'totalCount' => $totalCount,
        ]);
    }

    /** Catégories (level1) disponibles dans un territoire, avec slug + nombre. */
    private function categoriesFor(string $label): \Illuminate\Support\Collection
    {
        return Listing::query()
            ->where('status', 'published')
            ->where('territoire', $label)
            ->whereNotNull('category_level1')
            ->where('category_level1', '!=', '')
            ->selectRaw('category_level1, COUNT(*) as total')
            ->groupBy('category_level1')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->category_level1,
                'slug' => Str::slug($row->category_level1),
                'count' => (int) $row->total,
            ]);
    }

    /** Retrouve la valeur category_level1 réelle à partir de son slug. */
    private function resolveCategory(string $label, string $slug): ?string
    {
        return Listing::query()
            ->where('status', 'published')
            ->where('territoire', $label)
            ->whereNotNull('category_level1')
            ->distinct()
            ->pluck('category_level1')
            ->first(fn ($cat) => Str::slug($cat) === $slug);
    }
}
