<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Support\Recommandations;
use Illuminate\Support\Facades\Auth;

/**
 * Page « Pour vous » : la dernière sélection envoyée (articles encore en
 * ligne), complétée par les nouveautés qui correspondent aux goûts du membre.
 */
class RecommendationController extends Controller
{
    public function index()
    {
        $membre = Auth::user();

        $envoyees = Listing::query()
            ->with(['images', 'user'])
            ->withCount('favoritedBy')
            ->where('status', 'published')
            ->whereIn('id', fn ($q) => $q->select('listing_id')
                ->from('recommandations')
                ->where('user_id', $membre->id)
                ->where('envoye_at', '>=', now()->subDays(21)))
            ->latest()
            ->get();

        $articles = $envoyees
            ->concat(Recommandations::pour($membre, 24))
            ->unique('id')
            ->take(24)
            ->values();

        return view('account.recommendations', [
            'articles' => $articles,
            'selectedTerritoire' => $membre->territoire,
        ]);
    }
}
