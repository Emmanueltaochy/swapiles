<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Codes de parrainage : courts, lisibles, liés à l'île (LEA974, MARC972…).
 * Chaque membre peut le personnaliser UNE fois (mêmes règles d'unicité,
 * pas de mot grossier).
 */
class CodeParrainage
{
    public const ILES = [
        'La Réunion' => '974',
        'Martinique' => '972',
        'Guadeloupe' => '971',
        'Guyane' => '973',
        'Mayotte' => '976',
    ];

    /** Mots interdits (comparés après normalisation : 0→O, 1→I, 3→E, 4→A, 5→S…). */
    public const INTERDITS = [
        // Réservés
        'ADMIN', 'SWAPILES', 'SWAPILE', 'SUPPORT', 'MODERATEUR', 'STAFF', 'OFFICIEL',
        // Grossièretés, insultes, haine
        'MERDE', 'PUTE', 'PUTAIN', 'CONNARD', 'CONNASSE', 'CONNE', 'SALOPE', 'SALAUD', 'ENCULE', 'NIQUE',
        'NIQUER', 'BITE', 'BITTE', 'COUILLE', 'CHATTE', 'FOUTRE', 'BRANLE', 'PD', 'PEDE', 'TAPETTE',
        'GOUINE', 'NEGRE', 'BOUGNOUL', 'BICOT', 'YOUPIN', 'NAZI', 'HITLER', 'SEXE', 'SEX', 'PORNO', 'PORN',
        'FUCK', 'SHIT', 'BITCH', 'DICK', 'PUSSY', 'CUNT', 'ASSHOLE', 'NIGGER', 'NIGGA', 'FDP', 'NTM', 'TG',
        'CUL', 'ZIZI', 'TEUB', 'KONYAN', 'KOUYON', 'LANMANMAN', 'GRENN', 'KOKO', 'LAKOUYON',
    ];

    /** Mots trop courts pour être cherchés à l'intérieur d'un code (sinon faux positifs). */
    private const EXACTS = ['PD', 'TG', 'NIQUE', 'NIQUER', 'CUL', 'SEX', 'NTM', 'FDP', 'KOKO', 'CONNE', 'BITE', 'DICK', 'SHIT', 'PORN', 'NAZI', 'PUTE', 'ZIZI', 'TEUB'];

    public static function normaliser(string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($code)));
    }

    /** Code automatique : prénom (sans accents, 2 à 6 lettres) + numéro de l'île. */
    public static function generer(string $nom, ?string $territoire): string
    {
        $prenom = Str::of(Str::ascii(trim($nom)))->explode(' ')->first() ?: '';
        $lettres = Str::upper(substr(preg_replace('/[^A-Za-z]/', '', $prenom), 0, 6));
        if (strlen($lettres) < 2 || self::interdit($lettres)) {
            $lettres = 'SWAP';
        }

        $base = $lettres . (self::ILES[$territoire] ?? '974');

        if (! self::pris($base)) {
            return $base;
        }
        foreach (range('B', 'Z') as $lettre) {
            if (! self::pris($base . $lettre)) {
                return $base . $lettre;
            }
        }
        do {
            $code = $base . random_int(10, 999);
        } while (self::pris($code));

        return $code;
    }

    /** Code déjà utilisé par un membre (actuel ou ancien code). */
    public static function pris(string $code, ?int $saufUserId = null): bool
    {
        $code = self::normaliser($code);

        return DB::table('users')
            ->when($saufUserId, fn ($q) => $q->where('id', '!=', $saufUserId))
            ->where(fn ($q) => $q->where('code_parrainage', $code)->orWhere('code_parrainage_ancien', $code))
            ->exists();
    }

    public static function interdit(string $code): bool
    {
        $brut = self::normaliser($code);
        $lisible = strtr($brut, ['0' => 'O', '1' => 'I', '3' => 'E', '4' => 'A', '5' => 'S', '7' => 'T', '8' => 'B', '@' => 'A']);
        $lettres = preg_replace('/[^A-Z]/', '', $lisible);

        foreach (self::INTERDITS as $mot) {
            if (in_array($mot, self::EXACTS, true)) {
                if ($lettres === $mot || $brut === $mot) {
                    return true;
                }

                continue;
            }
            if (str_contains($lisible, $mot) || str_contains($lettres, $mot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie un code personnalisé. Renvoie un message d'erreur, ou null si valide.
     */
    public static function erreurPersonnalisation(string $saisie, int $userId): ?string
    {
        $code = self::normaliser($saisie);

        if (strlen($code) < 4 || strlen($code) > 12) {
            return 'Ton code doit faire entre 4 et 12 caractères (lettres et chiffres).';
        }
        if (! preg_match('/[A-Z]/', $code)) {
            return 'Ton code doit contenir au moins une lettre.';
        }
        if (self::interdit($code)) {
            return 'Ce code n’est pas autorisé. Choisis-en un autre 🙂';
        }
        if (self::pris($code, $userId)) {
            return 'Ce code est déjà pris. Essaie une variante (ajoute ton île, un chiffre…).';
        }

        return null;
    }
}
