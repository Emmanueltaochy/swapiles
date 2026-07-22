<?php

namespace App\Support;

/**
 * Générateur de graphiques SVG inline (sans dépendance JS, compatible CSP et
 * mode sombre Filament). Utilisé par le tableau de bord et la page d'analyse
 * avancée pour afficher des courbes d'évolution et des barres.
 */
class Charts
{
    /**
     * Courbe(s) d'évolution en aire.
     *
     * @param  array<int,string>  $labels   Étiquettes de l'axe X.
     * @param  array<int,array{name:string,color:string,data:array<int,float|int>}>  $series
     */
    public static function line(array $labels, array $series, int $height = 220): string
    {
        $width = 900; // viewBox largeur logique (le SVG est responsive à 100%)
        $padL = 44; $padR = 16; $padT = 16; $padB = 28;
        $plotW = $width - $padL - $padR;
        $plotH = $height - $padT - $padB;

        $n = max(count($labels), 1);

        // Valeur max sur toutes les séries (au moins 1 pour éviter la division par 0).
        $max = 0;
        foreach ($series as $s) {
            foreach ($s['data'] as $v) {
                $max = max($max, (float) $v);
            }
        }
        $niceMax = self::niceCeil($max);

        $x = function (int $i) use ($padL, $plotW, $n) {
            return $padL + ($n <= 1 ? $plotW / 2 : $plotW * $i / ($n - 1));
        };
        $y = function (float $v) use ($padT, $plotH, $niceMax) {
            return $padT + $plotH - ($niceMax > 0 ? $plotH * $v / $niceMax : 0);
        };

        $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" width="100%" preserveAspectRatio="none" '
            . 'style="display:block;max-width:100%;height:auto;font-family:inherit;">';

        // Lignes de grille horizontales + libellés Y (4 paliers).
        for ($g = 0; $g <= 4; $g++) {
            $val = $niceMax * $g / 4;
            $yy = $y($val);
            $svg .= '<line x1="' . $padL . '" y1="' . round($yy, 1) . '" x2="' . ($width - $padR) . '" y2="' . round($yy, 1)
                . '" stroke="currentColor" stroke-opacity="0.12" stroke-width="1" />';
            $svg .= '<text x="' . ($padL - 8) . '" y="' . round($yy + 3, 1) . '" text-anchor="end" '
                . 'font-size="11" fill="currentColor" fill-opacity="0.45">' . self::shortNum($val) . '</text>';
        }

        // Étiquettes X espacées (max ~8).
        $step = (int) ceil($n / 8);
        for ($i = 0; $i < $n; $i += max($step, 1)) {
            $svg .= '<text x="' . round($x($i), 1) . '" y="' . ($height - 8) . '" text-anchor="middle" '
                . 'font-size="10" fill="currentColor" fill-opacity="0.45">' . htmlspecialchars((string) ($labels[$i] ?? ''), ENT_QUOTES) . '</text>';
        }

        // Chaque série : aire dégradée + ligne.
        $gid = 0;
        foreach ($series as $s) {
            $data = $s['data'];
            $color = $s['color'];
            $gid++;
            $id = 'swpgrad' . $gid;

            $linePts = [];
            foreach ($data as $i => $v) {
                $linePts[] = round($x($i), 1) . ',' . round($y((float) $v), 1);
            }
            if (empty($linePts)) {
                continue;
            }

            // Aire (sous la courbe).
            $first = $x(0);
            $last = $x(count($data) - 1);
            $baseY = $y(0);
            $areaPath = 'M ' . round($first, 1) . ',' . round($baseY, 1)
                . ' L ' . implode(' L ', $linePts)
                . ' L ' . round($last, 1) . ',' . round($baseY, 1) . ' Z';

            $svg .= '<defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="0" y2="1">'
                . '<stop offset="0%" stop-color="' . $color . '" stop-opacity="0.30" />'
                . '<stop offset="100%" stop-color="' . $color . '" stop-opacity="0.02" />'
                . '</linearGradient></defs>';
            $svg .= '<path d="' . $areaPath . '" fill="url(#' . $id . ')" />';
            $svg .= '<polyline points="' . implode(' ', $linePts) . '" fill="none" stroke="' . $color
                . '" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />';

            // Point final.
            $lastV = end($data);
            $svg .= '<circle cx="' . round($last, 1) . '" cy="' . round($y((float) $lastV), 1) . '" r="3.5" fill="' . $color . '" />';
        }

        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Diagramme en barres verticales (ex : activité par heure).
     *
     * @param  array<int,string>  $labels
     * @param  array<int,float|int>  $data
     */
    public static function bars(array $labels, array $data, string $color = '#0d9488', int $height = 180): string
    {
        $width = 900;
        $padL = 36; $padR = 12; $padT = 12; $padB = 26;
        $plotW = $width - $padL - $padR;
        $plotH = $height - $padT - $padB;
        $n = max(count($data), 1);
        $max = self::niceCeil((float) (max($data ?: [0])));

        $bw = $plotW / $n;
        $barW = $bw * 0.64;

        $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" width="100%" preserveAspectRatio="none" '
            . 'style="display:block;max-width:100%;height:auto;font-family:inherit;">';

        for ($g = 0; $g <= 2; $g++) {
            $val = $max * $g / 2;
            $yy = $padT + $plotH - ($max > 0 ? $plotH * $val / $max : 0);
            $svg .= '<line x1="' . $padL . '" y1="' . round($yy, 1) . '" x2="' . ($width - $padR) . '" y2="' . round($yy, 1)
                . '" stroke="currentColor" stroke-opacity="0.12" />';
            $svg .= '<text x="' . ($padL - 6) . '" y="' . round($yy + 3, 1) . '" text-anchor="end" font-size="10" '
                . 'fill="currentColor" fill-opacity="0.45">' . self::shortNum($val) . '</text>';
        }

        foreach ($data as $i => $v) {
            $h = $max > 0 ? $plotH * (float) $v / $max : 0;
            $bx = $padL + $bw * $i + ($bw - $barW) / 2;
            $by = $padT + $plotH - $h;
            $svg .= '<rect x="' . round($bx, 1) . '" y="' . round($by, 1) . '" width="' . round($barW, 1)
                . '" height="' . round($h, 1) . '" rx="2" fill="' . $color . '" fill-opacity="0.85" />';
            if ($i % max((int) ceil($n / 12), 1) === 0) {
                $svg .= '<text x="' . round($bx + $barW / 2, 1) . '" y="' . ($height - 8) . '" text-anchor="middle" '
                    . 'font-size="9" fill="currentColor" fill-opacity="0.45">' . htmlspecialchars((string) ($labels[$i] ?? ''), ENT_QUOTES) . '</text>';
            }
        }

        $svg .= '</svg>';

        return $svg;
    }

    private static function niceCeil(float $max): float
    {
        if ($max <= 0) {
            return 1;
        }
        $pow = pow(10, floor(log10($max)));
        $frac = $max / $pow;
        $niceFrac = $frac <= 1 ? 1 : ($frac <= 2 ? 2 : ($frac <= 5 ? 5 : 10));

        return $niceFrac * $pow;
    }

    private static function shortNum(float $v): string
    {
        if ($v >= 1000000) {
            return rtrim(rtrim(number_format($v / 1000000, 1, '.', ''), '0'), '.') . 'M';
        }
        if ($v >= 1000) {
            return rtrim(rtrim(number_format($v / 1000, 1, '.', ''), '0'), '.') . 'k';
        }

        return (string) (int) round($v);
    }
}
