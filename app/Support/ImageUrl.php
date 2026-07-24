<?php

namespace App\Support;

use Illuminate\Support\Str;

class ImageUrl
{
    /**
     * Normalise une URL d'image en URL ABSOLUE.
     *
     * Les images d'annonces sont stockées sous la forme « /storage/listings/… ».
     * Côté front (même domaine) le navigateur résout ce chemin sans problème,
     * mais Filament (ImageColumn) tente de vérifier l'existence du fichier sur
     * le disque « public » avec ce chemin préfixé « /storage/ » — ce qui échoue
     * et n'affiche aucune image. En renvoyant une URL absolue, Filament l'utilise
     * telle quelle et l'image s'affiche.
     */
    public static function absolute(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://', 'data:'])) {
            return $url;
        }

        $base = rtrim((string) env('APP_CANONICAL_URL', 'https://swapiles.com'), '/');

        return $base . '/' . ltrim($url, '/');
    }
}
