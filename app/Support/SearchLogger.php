<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\SearchLog;
use App\Models\SearchNoResult;

/**
 * Journal des recherches, pour l'admin :
 *  - toutes les recherches tapées (ce que les gens cherchent) ;
 *  - les recherches « sans résultat » : uniquement ce qui n'existe nulle part
 *    sur la plateforme. Une recherche vide à cause d'un filtre (île,
 *    catégorie, prix) n'y figure pas : l'article existe, il était filtré.
 */
class SearchLogger
{
    /**
     * Enregistre la recherche (hors robots).
     *
     * @return string|null  Le terme brut quand la page n'a rien trouvé (pour proposer une alerte), sinon null.
     */
    public static function record(string $rawQuery, int $total, ?int $userId, ?string $userAgent, ?string $visitorId = null, ?string $territoire = null): ?string
    {
        $raw = trim($rawQuery);

        if ($raw === '' || BotDetector::isBot($userAgent)) {
            return null;
        }

        $terme = SearchNoResult::normalize($raw);

        try {
            SearchLog::create([
                'term' => mb_substr($terme, 0, 120),
                'raw_term' => mb_substr($raw, 0, 255),
                'resultats' => $total,
                'user_id' => $userId,
                'visitor_id' => $visitorId,
                'territoire' => $territoire,
                'created_at' => now(),
            ]);

            if ($total === 0 && ! self::existeSurLaPlateforme($terme)) {
                SearchNoResult::create([
                    'term' => $terme,
                    'raw_term' => mb_substr($raw, 0, 255),
                    'user_id' => $userId,
                    'visitor_id' => $visitorId,
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $total === 0 ? $raw : null;
    }

    /** Le terme trouve-t-il au moins une annonce en ligne, toutes îles et sans filtre ? */
    public static function existeSurLaPlateforme(string $terme): bool
    {
        $terme = trim($terme);
        if ($terme === '') {
            return false;
        }

        // « femme », « high-tech »… : un nom de catégorie, qui existe forcément.
        if (Categories::niveau1DepuisTexte($terme)) {
            return true;
        }

        return RechercheTexte::appliquer(Listing::query()->where('status', 'published'), $terme)->exists();
    }
}
