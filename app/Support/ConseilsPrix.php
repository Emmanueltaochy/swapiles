<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Conseil au vendeur quand une annonce a beaucoup de vues mais pas d'acheteur
 * après 14 jours. Si des articles similaires se vendent moins cher sur
 * Swap'Îles (prix repères), on le lui dit ; sinon, des astuces de vente.
 *
 * Garde-fous : un seul conseil par annonce (renouvelable après 60 jours), un
 * seul conseil par vendeur et par semaine (celui de son annonce la plus vue).
 */
class ConseilsPrix
{
    public const TYPE = 'listing_price_advice';

    public const APRES_JOURS = 14;

    public const VUES_MIN = 30;

    public const RENOUVELER_JOURS = 60;

    public const PAR_VENDEUR_JOURS = 7;

    public const MAX_PAR_PASSAGE = 300;

    /** Envoie les conseils du jour. Retourne le nombre de conseils envoyés. */
    public static function envoyer(): int
    {
        if (! Schema::hasColumn('listings', 'conseil_prix_at')) {
            return 0;
        }

        // Vendeurs déjà conseillés cette semaine.
        $dejaConseilles = Listing::query()->where('conseil_prix_at', '>=', now()->subDays(self::PAR_VENDEUR_JOURS))
            ->distinct()->pluck('user_id')->all();

        $annonces = Listing::query()
            ->where('status', 'published')
            ->whereIn('listing_type', ['achat', 'negoce-prix'])
            ->where('price', '>', 0)
            ->where('views_count', '>=', self::VUES_MIN)
            ->where('created_at', '<=', now()->subDays(self::APRES_JOURS))
            ->where(fn ($q) => $q->whereNull('conseil_prix_at')->orWhere('conseil_prix_at', '<', now()->subDays(self::RENOUVELER_JOURS)))
            // Prix déjà baissé ces 14 derniers jours : le vendeur a déjà agi.
            ->where(fn ($q) => $q->whereNull('prix_remonte_at')->orWhere('prix_remonte_at', '<', now()->subDays(self::APRES_JOURS)))
            ->whereNotIn('user_id', $dejaConseilles)
            ->whereHas('user', fn ($q) => $q->where(fn ($b) => $b->whereNull('is_banned')->orWhere('is_banned', false)))
            ->orderByDesc('views_count')
            ->limit(self::MAX_PAR_PASSAGE * 3)
            ->get()
            ->unique('user_id')
            ->take(self::MAX_PAR_PASSAGE);

        $envoyes = 0;
        foreach ($annonces as $annonce) {
            try {
                [$titre, $message] = self::texte($annonce);
                Notification::create([
                    'user_id' => $annonce->user_id,
                    'type' => self::TYPE,
                    'title' => $titre,
                    'message' => $message,
                    'url' => route('account.listings.edit', $annonce, absolute: false),
                ]);
                $annonce->forceFill(['conseil_prix_at' => now()])->saveQuietly();
                $envoyes++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $envoyes;
    }

    /** @return array{0:string,1:string} titre et message */
    public static function texte(Listing $annonce): array
    {
        $vues = (int) $annonce->views_count;
        $titre = '💡 « ' . \Illuminate\Support\Str::limit($annonce->title, 40) . ' » intéresse : ' . $vues . ' vues';
        $prix = (float) $annonce->price;
        $reperes = PrixReperes::pour($annonce->category_level1, $annonce->category_level2, $annonce->category_level3, $annonce->territoire);

        if ($reperes && $prix > $reperes['median']) {
            return [$titre, 'Votre annonce a eu ' . $vues . ' vues mais pas encore d’acheteur. Sur Swap’Îles, les articles similaires se vendent autour de '
                . $reperes['median'] . ' € (le plus souvent entre ' . $reperes['bas'] . ' et ' . $reperes['haut'] . ' €). '
                . 'En baissant le prix d’au moins 10 %, votre annonce repasse en tête de liste et ceux qui l’ont en favori sont prévenus.'];
        }

        $astuces = 'Astuces : une première photo nette, à la lumière du jour'
            . ($annonce->requires_online_payment && $annonce->allows_colissimo ? '' : ', le paiement en ligne avec Colissimo pour vendre aux autres îles')
            . ', et un partage sur Facebook ou WhatsApp avec le bouton « Partager ».';

        return [$titre, 'Votre annonce a eu ' . $vues . ' vues mais pas encore d’acheteur. ' . $astuces
            . ' Une baisse de prix d’au moins 10 % la fait aussi repasser en tête de liste.'];
    }
}
