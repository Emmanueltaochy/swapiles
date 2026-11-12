<?php

namespace App\Support;

/**
 * Code pays attendu par Colissimo (2 lettres) : la France métropolitaine est
 * « FR », chaque Outre-mer a le sien (RE, GP, MQ, GF, YT…). Le code postal
 * fait foi ; sinon le pays saisi (« France », « La Réunion », « FR »…).
 */
class ColissimoPays
{
    /** Préfixes de codes postaux des Outre-mer (du plus précis au plus large). */
    private const PREFIXES = [
        '97133' => 'BL', // Saint-Barthélemy
        '97150' => 'MF', // Saint-Martin
        '971' => 'GP', '972' => 'MQ', '973' => 'GF', '974' => 'RE', '975' => 'PM', '976' => 'YT',
        '986' => 'WF', '987' => 'PF', '988' => 'NC',
    ];

    private const NOMS = [
        'france' => 'FR', 'france metropolitaine' => 'FR', 'metropole' => 'FR',
        'la reunion' => 'RE', 'reunion' => 'RE', 'guadeloupe' => 'GP', 'martinique' => 'MQ',
        'guyane' => 'GF', 'guyane francaise' => 'GF', 'mayotte' => 'YT',
        'saint-barthelemy' => 'BL', 'saint-martin' => 'MF', 'saint-pierre-et-miquelon' => 'PM',
        'belgique' => 'BE', 'suisse' => 'CH', 'luxembourg' => 'LU', 'monaco' => 'MC',
        'allemagne' => 'DE', 'espagne' => 'ES', 'italie' => 'IT', 'portugal' => 'PT',
    ];

    public static function code(?string $pays, ?string $codePostal): string
    {
        $cp = preg_replace('/\s+/', '', (string) $codePostal);
        if (preg_match('/^\d{5}$/', $cp)) {
            foreach (self::PREFIXES as $prefixe => $code) {
                if (str_starts_with($cp, (string) $prefixe)) {
                    return $code;
                }
            }
            $saisi = self::depuisTexte($pays);

            // Code postal français à 5 chiffres : la France, sauf pays européen explicitement saisi.
            return $saisi && $saisi !== 'FR' && ! in_array($saisi, self::PREFIXES, true) ? $saisi : 'FR';
        }

        return self::depuisTexte($pays) ?? 'FR';
    }

    private static function depuisTexte(?string $pays): ?string
    {
        $p = trim((string) $pays);
        if ($p === '') {
            return null;
        }
        if (preg_match('/^[A-Za-z]{2}$/', $p)) {
            return strtoupper($p);
        }
        $cle = strtolower(\Illuminate\Support\Str::ascii($p));

        return self::NOMS[$cle] ?? null;
    }
}
