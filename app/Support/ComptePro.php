<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\User;

/**
 * Compte professionnel (boutiques, marques) : une vente devient du commerce en
 * ligne, avec d'autres règles qu'entre particuliers.
 *
 *  - Prix affiché = prix payé : pas de protection acheteur ajoutée à la caisse
 *    (le client d'un pro est protégé par la loi : rétractation 14 jours,
 *    garantie de conformité). Swap'Îles prend une commission sur ce que reçoit
 *    le pro, invisible pour l'acheteur.
 *  - Le pro choisit sa remise : retrait en boutique (code à présenter), sa
 *    propre livraison (son prix, ou offerte), Colissimo, point relais.
 *  - Le pro est payé au retrait en boutique (code validé), à la réception, ou
 *    au plus tard N jours après l'envoi.
 *
 * Le mode pro ne s'applique qu'à un compte validé par l'admin (raison sociale
 * et SIRET renseignés) : sans validation, rien ne change pour personne.
 */
class ComptePro
{
    public static function estPro(?User $user): bool
    {
        return $user !== null
            && $user->pro_valide_at !== null
            && filled($user->pro_siret)
            && filled($user->pro_raison_sociale)
            && ! $user->is_banned;
    }

    public static function annonceDePro(?Listing $listing): bool
    {
        return $listing !== null && self::estPro($listing->user);
    }

    /** Taux de commission Swap'Îles du pro (ex. 0.05 = 5 %). */
    public static function taux(User $pro): float
    {
        $taux = $pro->pro_commission_taux !== null ? (float) $pro->pro_commission_taux : (float) config('pricing.pro_commission_rate', 0.05);

        return max(0.0, min(0.5, $taux));
    }

    /** Le pro propose le retrait dans sa boutique (adresse renseignée). */
    public static function retraitBoutique(User $pro): bool
    {
        return (bool) $pro->pro_retrait_boutique && filled($pro->pro_adresse);
    }

    /** Le pro livre lui-même, à son prix (0 = offerte). */
    public static function livraison(User $pro): bool
    {
        return (bool) $pro->pro_livraison;
    }

    public static function prixLivraison(User $pro): float
    {
        return max(0.0, round((float) $pro->pro_livraison_prix, 2));
    }

    /** SIRET lisible : 123 456 789 00012. */
    public static function siret(?string $siret): string
    {
        $chiffres = preg_replace('/\D/', '', (string) $siret);

        return strlen($chiffres) === 14
            ? substr($chiffres, 0, 3) . ' ' . substr($chiffres, 3, 3) . ' ' . substr($chiffres, 6, 3) . ' ' . substr($chiffres, 9)
            : (string) $siret;
    }

    /** Un SIRET valide a 14 chiffres et passe la clé de Luhn. */
    public static function siretValide(?string $siret): bool
    {
        $chiffres = preg_replace('/\D/', '', (string) $siret);
        if (strlen($chiffres) !== 14) {
            return false;
        }
        $somme = 0;
        foreach (str_split(strrev($chiffres)) as $i => $c) {
            $n = (int) $c * ($i % 2 === 1 ? 2 : 1);
            $somme += $n > 9 ? $n - 9 : $n;
        }

        // La Poste (SIREN 356000000) a une règle à part : somme des chiffres multiple de 5.
        return $somme % 10 === 0 || (str_starts_with($chiffres, '356000000') && array_sum(str_split($chiffres)) % 5 === 0);
    }

    /** Code à présenter en boutique (sans caractères ambigus). */
    public static function codeRetrait(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
