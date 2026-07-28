<?php

namespace App\Support;

use App\Models\SearchNoResult;

/**
 * Journalisation des recherches sans résultat (point 17). Isolé du contrôleur
 * pour être testable indépendamment de la requête de recherche (qui utilise des
 * fonctions SQL MySQL non dispo en SQLite de test).
 */
class SearchLogger
{
    /**
     * Enregistre la recherche si elle est textuelle, sans résultat, et non-bot.
     *
     * @return string|null  Le terme brut si journalisé (pour proposer une alerte), sinon null.
     */
    public static function record(string $rawQuery, int $total, ?int $userId, ?string $userAgent): ?string
    {
        $raw = trim($rawQuery);

        if ($raw === '' || $total > 0) {
            return null;
        }

        if (BotDetector::isBot($userAgent)) {
            return null;
        }

        try {
            SearchNoResult::create([
                'term' => SearchNoResult::normalize($raw),
                'raw_term' => mb_substr($raw, 0, 255),
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $raw;
    }
}
