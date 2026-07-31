<?php

namespace App\Support;

/**
 * Modération Partie 1 — détection par mots-clés, sans IA (instantané, gratuit,
 * déterministe). Deux signaux :
 *   - paiement HORS plateforme (wero, paypal, rib, iban…) → bloqué à l'envoi ;
 *   - échange de numéro / contact hors plateforme → simple signalement.
 *
 * Robuste aux variantes : casse, accents, espacements, caractères intercalés
 * (« w e r o », « r.i.b »), substitutions type leet (« payp4l », « ib4n »).
 */
class MessageModeration
{
    /** Marques de paiement NON ambiguës → blocage direct. */
    private const PAYMENT_TERMS_STRONG = [
        'wero', 'paylib', 'paypal', 'revolut',
        'western union', 'moneygram', 'virement', 'rib',
    ];

    /**
     * Termes de paiement qui sont AUSSI des prénoms/mots courants (« Lydia »,
     * « Iban ») : on ne les bloque que si un indice de paiement est présent
     * (chiffres d'un IBAN/montant, ou un verbe de paiement), pour ne pas
     * accuser à tort un message comme « Bonjour Lydia ».
     */
    private const PAYMENT_TERMS_CONTEXTUAL = ['lydia', 'iban'];

    /**
     * Détecte les mots-clés de paiement hors plateforme dans un message.
     *
     * @return array<int,string>  Les termes canoniques trouvés (vide si aucun).
     */
    public static function detectPayment(?string $body): array
    {
        $text = self::normalize((string) $body);

        if ($text === '') {
            return [];
        }

        $found = [];
        foreach (self::PAYMENT_TERMS_STRONG as $term) {
            if (preg_match('/' . self::termPattern($term) . '/u', $text)) {
                $found[] = $term;
            }
        }

        // Termes ambigus : uniquement si un autre signal de paiement est présent.
        $contextual = [];
        foreach (self::PAYMENT_TERMS_CONTEXTUAL as $term) {
            if (preg_match('/' . self::termPattern($term) . '/u', $text)) {
                $contextual[] = $term;
            }
        }
        if (! empty($contextual) && (! empty($found) || self::hasPaymentContext($text))) {
            $found = array_merge($found, $contextual);
        }

        return array_values(array_unique($found));
    }

    /** Indice de paiement à proximité (numéro long, ou verbe de paiement). */
    private static function hasPaymentContext(string $text): bool
    {
        // Un IBAN, un numéro de compte ou un montant comporte une suite de chiffres.
        if (preg_match('/\d{4,}/u', $text)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(envoi|envoie|envoye|paie|paye|payer|paiement|vire|virer|virement|compte|transfer|transfere|rembours|argent|banque|bancaire)\w*/u',
            $text
        );
    }

    /**
     * Détecte une demande / partage de numéro ou de contact hors plateforme.
     * Utilisé uniquement en signalement (jamais bloquant) et seulement sur les
     * premiers messages d'une conversation.
     */
    public static function detectPhone(?string $body): bool
    {
        $text = self::normalize((string) $body);

        if ($text === '') {
            return false;
        }

        // Applis de contact hors plateforme.
        if (preg_match('/\b(whats?\s*app|wsp|telegram|teleg|snap\s*chat|snap|signal)\b/u', $text)) {
            return true;
        }

        // Formulation « ton / votre numéro / num ».
        if (preg_match('/\b(ton|votre|te|mon)\b[\s\W_]*(num(ero)?|no|tel|telephone|portable)\b/u', $text)) {
            return true;
        }
        // « tel » seul est trop ambigu (« un tel article ») : on ne garde que
        // les formes non ambiguës, exigées avec un chiffre.
        if (preg_match('/\b(num(ero)?|telephone|portable|whatsapp)\b/u', $text) && preg_match('/\d/u', $text)) {
            return true;
        }

        // Numéro de téléphone DOM-TOM / métropole : +262 / +590 / +594 / +596 / 06 / 07
        // suivi d'assez de chiffres pour ressembler à un vrai numéro.
        if (preg_match('/(?<!\d)(?:\+?\s*262|\+?\s*59[046]|0\s*[67])(?:[\s.\-]*\d){6,}/u', $text)) {
            return true;
        }

        return false;
    }

    /** Titre du bandeau d'avertissement paiement (Option A). */
    public const PAYMENT_WARNING_TITLE = 'Attention — paiement hors plateforme';

    /**
     * Corps de l'avertissement paiement (lignes), réutilisé dans le bandeau
     * in-app et la notification au destinataire.
     *
     * @return array<int,string>
     */
    public static function paymentWarningLines(): array
    {
        return [
            "Swap'Îles ne peut pas sécuriser les paiements effectués en dehors de la plateforme.",
            "• Remise en main propre → privilégie les espèces, comptées sur place.",
            "• Envoi ou paiement à distance → utilise le paiement sécurisé Swap'Îles : les fonds sont conservés par la plateforme et versés au vendeur uniquement après confirmation de réception par l'acheteur.",
            "De nombreuses arnaques passent par des liens de paiement envoyés par SMS. Ne valide jamais un lien reçu ainsi.",
        ];
    }

    /** Normalise un texte : minuscules + suppression des accents. */
    private static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

        // Translittère les accents en ASCII (« ibán » → « iban ») ; //IGNORE
        // écarte le reste. On retire les éventuelles marques résiduelles (^, ~, `).
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii !== false && $ascii !== '') {
            $text = mb_strtolower(str_replace(['^', '~', '`'], '', $ascii));
        }

        return $text;
    }

    /**
     * Construit un motif regex pour un terme, tolérant aux séparateurs entre
     * lettres et aux substitutions leet courantes, avec une frontière de mot
     * pour limiter les faux positifs (« rib » dans « attribuer »).
     */
    private static function termPattern(string $term): string
    {
        $sep = '[\s\W_]*';
        $letters = preg_split('//u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = [];

        foreach ($letters as $ch) {
            if (trim($ch) === '') {
                continue; // les espaces des termes composés sont gérés par $sep
            }
            $parts[] = self::letterClass($ch);
        }

        return '(?<![a-z0-9])' . implode($sep, $parts) . '(?![a-z0-9])';
    }

    /** Classe de caractères pour une lettre (gère les substitutions leet). */
    private static function letterClass(string $ch): string
    {
        return match ($ch) {
            'a' => '[a@4]',
            'e' => '[e3]',
            'i' => '[i1!]',
            'o' => '[o0]',
            'l' => '[l1]',
            's' => '[s5$]',
            default => preg_quote($ch, '/'),
        };
    }
}
