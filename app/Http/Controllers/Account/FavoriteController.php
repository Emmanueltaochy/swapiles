<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Notification;
use App\Notifications\ListingFavoritedNotification;
use App\Support\AdminEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Annonces pour lesquelles l'utilisateur a fait une DEMANDE DE LIVRAISON
        // (bouton « Demander la livraison » qui ajoute aussi aux favoris).
        $interestIds = \App\Models\ListingInterest::where('buyer_id', $user->id)
            ->pluck('listing_id');

        // Comptes par onglet.
        $favListingIds = $user->favorites()->pluck('listings.id');
        $countAll = $favListingIds->count();
        $countLivraison = $favListingIds->intersect($interestIds)->count();
        $countDirect = $countAll - $countLivraison;

        $filter = request('filter', 'all');
        if (! in_array($filter, ['all', 'direct', 'livraison'], true)) {
            $filter = 'all';
        }

        $query = $user->favorites()->with('images', 'user');

        if ($filter === 'direct') {
            $query->whereNotIn('listings.id', $interestIds);
        } elseif ($filter === 'livraison') {
            $query->whereIn('listings.id', $interestIds);
        }

        $favorites = $query->latest('favorites.created_at')
            ->paginate(40)
            ->withQueryString();

        return view('account.favorites.index', [
            'favorites' => $favorites,
            'interestIds' => $interestIds,
            'filter' => $filter,
            'countAll' => $countAll,
            'countDirect' => $countDirect,
            'countLivraison' => $countLivraison,
        ]);
    }

    /**
     * Prévient le vendeur d'un nouveau favori, en REGROUPANT les favoris de la
     * journée en une seule notification.
     *
     * Avant : un favori = une notification = une sonnerie. Un vendeur dont les
     * annonces plaisent recevait des dizaines d'alertes par jour, et n'avait
     * d'autre issue que de tout couper — voire de supprimer son compte.
     *
     * Maintenant : la notification du jour est mise à jour et recompte
     * (« 3 personnes ont aimé vos annonces aujourd'hui »). Le vendeur voit la
     * même information, mais son téléphone ne sonne qu'une fois.
     */
    private function notifierFavori(Listing $listing, $user): void
    {
        // Les mises en favori sont coupées par défaut : sans demande du
        // vendeur, on ne crée même pas l'alerte (pas de badge rouge pour rien).
        if ($listing->user && ! $listing->user->veutEtrePrevenu('favorite_added')) {
            return;
        }

        $aujourdHui = Notification::query()
            ->where('user_id', $listing->user_id)
            ->where('type', 'favorite_added')
            ->where('created_at', '>=', now()->startOfDay())
            ->latest('id')
            ->first();

        if (! $aujourdHui) {
            Notification::create([
                'user_id' => $listing->user_id,
                'type' => 'favorite_added',
                'title' => 'Nouveau favori ❤️',
                'message' => $user->name . ' a ajouté « ' . $listing->title . ' » à ses favoris.',
                'url' => route('listings.show', $listing, absolute: false),
            ]);

            return;
        }

        // On recompte les favoris reçus aujourd'hui sur l'ensemble des annonces
        // du vendeur, plutôt que d'incrémenter un compteur qui pourrait dériver.
        $total = DB::table('favorites')
            ->join('listings', 'listings.id', '=', 'favorites.listing_id')
            ->where('listings.user_id', $listing->user_id)
            ->where('favorites.created_at', '>=', now()->startOfDay())
            ->count();

        $total = max($total, 2);

        // saveQuietly : on ne redéclenche pas de notification push. Le vendeur
        // a déjà été prévenu ce matin, l'information se met simplement à jour.
        $aujourdHui->forceFill([
            'title' => 'Vos annonces plaisent ❤️',
            'message' => $total . ' personnes ont ajouté vos annonces à leurs favoris aujourd’hui.',
            'url' => route('account.dashboard', absolute: false),
            'read_at' => null,
        ])->saveQuietly();
    }

    public function toggle(Listing $listing)
    {
        $user = auth()->user();

        if ($user->favorites()->where('listing_id', $listing->id)->exists()) {
            $user->favorites()->detach($listing->id);
            $favorited = false;
        } else {
            $user->favorites()->attach($listing->id);
            $favorited = true;

            if ($listing->user_id && $listing->user_id !== $user->id) {
                $this->notifierFavori($listing, $user);

                try {
                    $listing->user?->notify(new ListingFavoritedNotification($listing, $user));
                } catch (\Throwable $e) {
                    report($e);
                }

                AdminEvent::notify(
                    'Annonce ajoutée en favori',
                    ($user->name ?? 'Un membre') . ' a ajouté en favori : ' . $listing->title,
                    route('listings.show', $listing),
                    'favorite_added'
                );
            }
        }

        $count = $listing->favoritedBy()->count();

        if (request()->expectsJson()) {
            return response()->json([
                'favorited' => $favorited,
                'count' => $count,
            ]);
        }

        return back();
    }
}
