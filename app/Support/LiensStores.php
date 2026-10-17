<?php

namespace App\Support;

/**
 * Liens vers l'App Store et Google Play qui gardent la source de la visite
 * (une pub Meta, TikTok…), pour savoir dans la Play Console et App Store
 * Connect d'où viennent les installations.
 */
class LiensStores
{
    public const SESSION = 'swp_utm';

    /** Source de la visite en cours (paramètres utm_* de la page d'arrivée), ou null. */
    public static function utmDeLaVisite(): ?array
    {
        try {
            $utm = session(self::SESSION);
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($utm) && filled($utm['source'] ?? null) ? $utm : null;
    }

    public static function nettoyer(?string $valeur, int $max = 60): ?string
    {
        $valeur = mb_substr(preg_replace('/[^A-Za-z0-9_.\-]/', '', (string) $valeur), 0, $max);

        return $valeur !== '' ? $valeur : null;
    }

    /** @param  array{source?:?string,medium?:?string,campaign?:?string}|null  $utm */
    public static function android(?array $utm = null): string
    {
        $utm ??= self::utmDeLaVisite() ?? ['source' => 'swapiles.com', 'medium' => 'bandeau'];
        $referrer = http_build_query(array_filter([
            'utm_source' => $utm['source'] ?? null,
            'utm_medium' => $utm['medium'] ?? null,
            'utm_campaign' => $utm['campaign'] ?? null,
        ]));

        return 'https://play.google.com/store/apps/details?id=' . config('deeplinks.android.package', 'com.swapiles.app')
            . '&referrer=' . rawurlencode($referrer);
    }

    /**
     * App Store : le « jeton de campagne » d'Apple (ct) n'est compté qu'avec
     * le jeton fournisseur (pt) d'App Store Connect, à mettre dans
     * APP_STORE_PROVIDER_TOKEN. Sans lui, lien simple.
     */
    public static function ios(?array $utm = null): string
    {
        $base = (string) config('deeplinks.stores.ios');
        $pt = config('deeplinks.app_store_provider_token');
        $utm ??= self::utmDeLaVisite();

        if (blank($pt) || ! $utm) {
            return $base;
        }

        $ct = mb_substr(implode('_', array_filter([$utm['source'] ?? null, $utm['campaign'] ?? null])), 0, 40);

        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query(['pt' => $pt, 'ct' => $ct, 'mt' => 8]);
    }
}
