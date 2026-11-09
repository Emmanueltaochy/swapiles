<?php

namespace App\Support\Agent;

/** Lecture d'un e-mail brut (en-têtes, texte, sans la partie citée). */
class Courriel
{
    /** @return array<string,string> en-têtes en minuscules, décodés */
    public static function entetes(string $brut): array
    {
        $tete = preg_split("/\r?\n\r?\n/", $brut, 2)[0] ?? '';
        $tete = preg_replace("/\r?\n[ \t]+/", ' ', $tete); // lignes repliées
        $res = [];
        foreach (preg_split("/\r?\n/", $tete) as $l) {
            if (preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', $l, $m)) {
                $cle = strtolower($m[1]);
                $res[$cle] ??= self::decoderEntete($m[2]);
            }
        }

        return $res;
    }

    public static function decoderEntete(string $v): string
    {
        $d = @iconv_mime_decode($v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return trim($d !== false ? $d : $v);
    }

    public static function adresse(?string $de): ?string
    {
        if (! $de) {
            return null;
        }
        if (preg_match('/<([^>]+)>/', $de, $m)) {
            $de = $m[1];
        }
        $de = strtolower(trim($de, " \t\"'"));

        return filter_var($de, FILTER_VALIDATE_EMAIL) ? $de : null;
    }

    /** Texte lisible du message (partie texte, sinon HTML nettoyé). */
    public static function texte(string $brut): string
    {
        [$texte, $html] = self::parties($brut);
        $t = $texte !== '' ? $texte : trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div)[^>]*>/i', "\n", preg_replace('/<(style|script)[^>]*>.*?<\/\1>/is', '', $html))), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return self::sansCitation($t);
    }

    /** @return array{0:string,1:string} [texte, html] */
    private static function parties(string $brut, int $profondeur = 0): array
    {
        $morceaux = preg_split("/\r?\n\r?\n/", $brut, 2);
        $tete = self::entetes($brut);
        $corps = $morceaux[1] ?? '';
        $type = strtolower($tete['content-type'] ?? 'text/plain');

        if (str_starts_with($type, 'multipart/') && $profondeur < 5 && preg_match('/boundary="?([^";]+)"?/i', $tete['content-type'] ?? '', $m)) {
            $texte = $html = '';
            foreach (explode('--' . $m[1], $corps) as $partie) {
                $partie = ltrim($partie, "\r\n");
                if ($partie === '' || str_starts_with($partie, '--')) {
                    continue;
                }
                [$t, $h] = self::parties($partie, $profondeur + 1);
                $texte = $texte !== '' ? $texte : $t;
                $html = $html !== '' ? $html : $h;
            }

            return [$texte, $html];
        }
        if (! str_starts_with($type, 'text/')) {
            return ['', ''];
        }
        $encodage = strtolower($tete['content-transfer-encoding'] ?? '');
        $corps = match ($encodage) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $corps)),
            'quoted-printable' => quoted_printable_decode($corps),
            default => $corps,
        };
        $jeu = preg_match('/charset="?([A-Za-z0-9_-]+)"?/i', $tete['content-type'] ?? '', $c) ? strtoupper($c[1]) : 'UTF-8';
        if ($jeu !== 'UTF-8') {
            $converti = @mb_convert_encoding($corps, 'UTF-8', $jeu);
            $corps = is_string($converti) ? $converti : $corps;
        }
        $corps = trim($corps);

        return str_starts_with($type, 'text/html') ? ['', $corps] : [$corps, ''];
    }

    /** Coupe la partie citée (notre message d'origine) et limite la longueur. */
    public static function sansCitation(string $t): string
    {
        $lignes = [];
        foreach (preg_split("/\r?\n/", $t) as $l) {
            if (preg_match('/^\s*>/', $l)
                || preg_match('/^\s*(Le .{3,80}a écrit\s*:|On .{3,80}wrote\s*:|-{2,}\s*(Message d.origine|Original Message)|De\s*:.+@|From\s*:.+@|Envoyé de mon )/iu', $l)) {
                break;
            }
            $lignes[] = $l;
        }
        $t = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lignes)));

        return mb_substr($t, 0, 3000);
    }
}
