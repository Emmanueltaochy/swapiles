<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\PanierArticle;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Lots : plusieurs articles du même dressing, une seule livraison, un seul
 * paiement, et la remise éventuelle du vendeur.
 *
 * Chaque article garde sa propre vente (transaction) : versement au vendeur
 * et remboursement article par article fonctionnent comme avant. Le lot les
 * regroupe et la vente « principale » (la première) porte la livraison.
 */
class Lots
{
    public const MAX_ARTICLES = 10;

    public const RESERVATION_MINUTES = 15;

    public const REMISES = [0, 5, 10, 15, 20];

    public const MINIMUMS = [2, 3];

    public const POIDS_MAX_COLISSIMO = 30.0;

    /** L'article peut-il être acheté en ligne (donc mis dans un lot) ? */
    public static function eligible(Listing $listing): bool
    {
        return $listing->status === 'published'
            && in_array($listing->listing_type, ['achat', 'negoce-prix'], true)
            && (float) $listing->price > 0
            && $listing->isOnlinePayable();
    }

    /** Les articles du lot en préparation chez ce vendeur (les indisponibles sont retirés). */
    public static function articles(User $acheteur, User|int $vendeur): Collection
    {
        $vendeurId = $vendeur instanceof User ? $vendeur->id : $vendeur;

        $lignes = PanierArticle::query()
            ->where('buyer_id', $acheteur->id)
            ->whereHas('listing', fn ($query) => $query->where('user_id', $vendeurId))
            ->with('listing.images', 'listing.user')
            ->orderBy('id')
            ->get();

        $indisponibles = $lignes->filter(fn ($l) => ! $l->listing || ! self::eligible($l->listing) || self::vendu($l->listing));
        if ($indisponibles->isNotEmpty()) {
            PanierArticle::whereIn('id', $indisponibles->pluck('id'))->delete();
        }

        return $lignes->diff($indisponibles)->map->listing->values();
    }

    /** Nombre d'articles par vendeur dans les lots en préparation de l'acheteur. */
    public static function resume(User $acheteur): Collection
    {
        return PanierArticle::query()
            ->where('panier_articles.buyer_id', $acheteur->id)
            ->join('listings', 'listings.id', '=', 'panier_articles.listing_id')
            ->where('listings.status', 'published')
            ->selectRaw('listings.user_id as vendeur_id, COUNT(*) as nombre')
            ->groupBy('listings.user_id')
            ->pluck('nombre', 'vendeur_id');
    }

    public static function contient(User $acheteur, Listing $listing): bool
    {
        return PanierArticle::where('buyer_id', $acheteur->id)->where('listing_id', $listing->id)->exists();
    }

    /** Ajoute l'article au lot ; renvoie un message d'erreur, ou null si c'est fait. */
    public static function ajouter(User $acheteur, Listing $listing): ?string
    {
        if ($listing->user_id === $acheteur->id) {
            return 'C’est votre propre article.';
        }
        if (! self::eligible($listing) || self::vendu($listing)) {
            return 'Cet article ne peut pas être acheté en ligne.';
        }
        if (self::reservePourUnAutre($listing, $acheteur)) {
            return 'Cet article est en cours d’achat par quelqu’un d’autre.';
        }

        $deja = self::articles($acheteur, $listing->user_id);
        if ($deja->contains('id', $listing->id)) {
            return null;
        }
        if ($deja->count() >= self::MAX_ARTICLES) {
            return 'Un lot contient ' . self::MAX_ARTICLES . ' articles au maximum.';
        }
        // Une seule expédition : tous les articles partent de la même île.
        if ($deja->isNotEmpty() && $deja->first()->territoire !== $listing->territoire) {
            return 'Cet article est sur une autre île que le reste du lot : achetez-le séparément.';
        }
        if ($deja->isNotEmpty() && self::modesCommuns($deja->concat([$listing]))['aucun_mode']) {
            return 'Cet article ne peut pas être livré de la même façon que le reste du lot.';
        }

        PanierArticle::firstOrCreate(['buyer_id' => $acheteur->id, 'listing_id' => $listing->id]);

        return null;
    }

    public static function retirer(User $acheteur, Listing $listing): void
    {
        PanierArticle::where('buyer_id', $acheteur->id)->where('listing_id', $listing->id)->delete();
    }

    public static function vider(User $acheteur, Collection $listings): void
    {
        PanierArticle::where('buyer_id', $acheteur->id)->whereIn('listing_id', $listings->pluck('id'))->delete();
    }

    /** Déjà payé par quelqu'un ? */
    public static function vendu(Listing $listing): bool
    {
        return $listing->status === 'sold'
            || Transaction::where('listing_id', $listing->id)->whereIn('status', ['paid', 'completed'])->exists();
    }

    /**
     * Un autre acheteur est en train de payer cet article dans un lot : il
     * lui est réservé quelques minutes, le temps du paiement.
     */
    public static function reservePourUnAutre(Listing $listing, ?User $moi): bool
    {
        return Transaction::query()
            ->where('listing_id', $listing->id)
            ->whereNotNull('lot_id')
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(self::RESERVATION_MINUTES))
            ->when($moi, fn ($query) => $query->where('buyer_id', '!=', $moi->id))
            ->exists();
    }

    /**
     * Modes de livraison possibles pour TOUT le lot.
     *
     * @return array{hand_delivery: bool, colissimo: bool, relay: Collection, aucun_mode: bool, poids: float}
     */
    public static function modesCommuns(Collection $listings): array
    {
        $poids = (float) $listings->sum(fn ($l) => (float) ($l->weight_kg ?: 0.5));
        $main = $listings->every(fn ($l) => (bool) ($l->allows_hand_delivery ?? $l->pickup_enabled ?? true));
        // Colissimo : il faut aussi l'adresse d'expédition du vendeur.
        $vendeur = $listings->first()?->user;
        $colissimo = $poids <= self::POIDS_MAX_COLISSIMO
            && $vendeur && (filled($vendeur->address_line1) || filled($vendeur->postal_code))
            && $listings->every(fn ($l) => ($l->requires_online_payment ?? false) && ($l->allows_colissimo ?? false));

        $relais = collect();
        // Charte : poids total du colis limité au point relais.
        if (config('features.relay_points') && $listings->isNotEmpty() && $poids <= (float) config('relais.poids_max_kg', 15)) {
            $relais = $listings->first()->effectiveRelayPoints();
            foreach ($listings->slice(1) as $l) {
                $ids = $l->effectiveRelayPoints()->pluck('id');
                $relais = $relais->whereIn('id', $ids)->values();
            }
        }

        return [
            'hand_delivery' => $main,
            'colissimo' => $colissimo,
            'relay' => $relais,
            'aucun_mode' => ! $main && ! $colissimo && $relais->isEmpty(),
            'poids' => round($poids, 2),
        ];
    }

    /** Remise du vendeur applicable à ce nombre d'articles (en %). */
    public static function remisePct(User $vendeur, int $nombre): int
    {
        $pct = (int) ($vendeur->lot_remise_pct ?? 0);
        $min = (int) ($vendeur->lot_remise_min ?? 2);

        return in_array($pct, self::REMISES, true) && $pct > 0 && $nombre >= max(2, $min) ? $pct : 0;
    }

    /**
     * Prix remisés article par article (centimes). La somme est exactement le
     * total remisé arrondi : les centimes d'arrondi vont au dernier article.
     *
     * @param  int[]  $prix
     * @return int[]
     */
    public static function prixRemises(array $prix, int $pct): array
    {
        if ($pct <= 0) {
            return array_values($prix);
        }

        $cible = (int) round(array_sum($prix) * (100 - $pct) / 100);
        $remises = array_map(fn ($p) => (int) floor($p * (100 - $pct) / 100), array_values($prix));
        $remises[count($remises) - 1] += $cible - array_sum($remises);

        return $remises;
    }

    /**
     * Répartit un montant (centimes) proportionnellement aux poids donnés ;
     * la somme des parts est exactement le montant.
     *
     * @param  int[]  $poids
     * @return int[]
     */
    public static function repartir(int $montant, array $poids): array
    {
        $poids = array_values($poids);
        $total = array_sum($poids);
        if ($montant <= 0 || $total <= 0) {
            return array_fill(0, count($poids), 0);
        }

        $parts = array_map(fn ($p) => (int) floor($montant * $p / $total), $poids);
        $reste = $montant - array_sum($parts);

        // Les centimes restants vont aux plus gros poids.
        arsort($poids);
        foreach (array_keys($poids) as $i) {
            if ($reste <= 0) {
                break;
            }
            $parts[$i]++;
            $reste--;
        }

        return $parts;
    }
}
