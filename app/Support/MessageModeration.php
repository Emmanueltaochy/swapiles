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
    /** Mots-clés de paiement hors plateforme à détecter. */
    private const PAYMENT_TERMS = [
        'wero', 'paylib', 'lydia', 'paypal', 'revolut',
        'western union', 'moneygram', 'virement', 'rib', 'iban',
    ];

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
        foreach (self::PAYMENT_TERMS as $term) {
            if (preg_match('/' . self::termPattern($term) . '/u', $text)) {
                $found[] = $term;
            }
        }

        return array_values(array_unique($found));
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
        if (preg_match('/\b(num(ero)?|tel|telephone|portable|whatsapp)\b/u', $text) && preg_match('/\d/u', $text)) {
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

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii !== false) {
            // iconv peut produire des marques (^, ~) : on les retire.
            $text = preg_replace('/[^\p{L}\p{N}\s\W_]/u', '', $ascii) ?? $ascii;
            $text = mb_strtolower($text);
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
