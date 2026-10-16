<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\LiveSession;
use App\Models\User;

/**
 * Exclusivités live : des articles déposés mais cachés partout (recherche,
 * accueil, profil, stories, alertes) jusqu'à leur dévoilement en direct.
 * Pendant la phase de test, réservé à ceux qui ont accès au live.
 */
class ExclusiviteLive
{
    public const STATUT = 'live';

    public static function disponible(?User $user): bool
    {
        return LiveAcces::visible($user);
    }

    /** Dévoilée en direct : l'article passe en ligne et s'achète tout de suite. */
    public static function devoiler(Listing $article): void
    {
        if ($article->status === self::STATUT) {
            $article->update(['status' => 'published']);
        }
    }

    /**
     * Fin du live : les exclusivités pas encore dévoilées passent en ligne,
     * sans travail en plus pour le vendeur.
     */
    public static function publierRestantes(LiveSession $live): int
    {
        return Listing::query()
            ->whereIn('id', $live->articles()->select('listings.id'))
            ->where('status', self::STATUT)
            ->update(['status' => 'published']);
    }
}
