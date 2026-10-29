<?php

namespace App\Support;

use App\Models\Listing;
use Illuminate\Support\Facades\Cache;

/**
 * Prix repères pour le dépôt : à combien se vendent les articles de la même
 * catégorie sur Swap'Îles (annonces en ligne et vendues des 12 derniers mois).
 * On prend le niveau le plus précis qui a assez d'annonces : type d'article
 * sur l'île, puis type sur toutes les îles, puis sous-catégorie.
 * Fourchette = du 1er au 3e quartile (la moitié des prix du milieu).
 */
class PrixReperes
{
    /** En dessous, la fourchette ne veut rien dire. */
    public const MINIMUM = 5;

    /**
     * @return array{bas:int,median:int,haut:int,nombre:int,libelle:string,ile:?string}|null
     */
    public static function pour(?string $c1, ?string $c2, ?string $c3, ?string $ile = null): ?array
    {
        [$c1, $c2, $c3] = array_map(fn ($c) => filled($c) ? mb_strtolower(trim((string) $c)) : null, [$c1, $c2, $c3]);
        if (! $c1 || ! $c2) {
            return null;
        }
        $ile = filled($ile) ? trim((string) $ile) : null;

        $essais = array_values(array_filter([
            $c3 && $ile ? [$c3, $ile] : null,
            $c3 ? [$c3, null] : null,
            $ile ? [null, $ile] : null,
            [null, null],
        ]));

        foreach ($essais as [$type, $surIle]) {
            $prix = self::prix($c1, $c2, $type, $surIle);
            if (count($prix) >= self::MINIMUM) {
                return [
                    'bas' => self::quartile($prix, 0.25),
                    'median' => self::quartile($prix, 0.5),
                    'haut' => self::quartile($prix, 0.75),
                    'nombre' => count($prix),
                    'libelle' => Categories::label($type ?? $c2) ?? ($type ?? $c2),
                    'ile' => $surIle,
                ];
            }
        }

        return null;
    }

    /** @return array<int,float> prix triés */
    private static function prix(string $c1, string $c2, ?string $c3, ?string $ile): array
    {
        // Mis en cache 1 h : les prix d'une catégorie bougent peu, et le dépôt reste rapide.
        return Cache::remember('prix-reperes:' . md5(json_encode([$c1, $c2, $c3, $ile])), 3600, function () use ($c1, $c2, $c3, $ile) {
            return Listing::query()
                ->whereIn('status', ['published', 'sold'])
                ->whereIn('listing_type', ['achat', 'negoce-prix'])
                ->where('price', '>', 0)
                ->where('created_at', '>=', now()->subYear())
                ->whereRaw('LOWER(category_level1) = ?', [$c1])
                ->whereRaw('LOWER(category_level2) = ?', [$c2])
                ->when($c3, fn ($q) => $q->whereRaw('LOWER(category_level3) = ?', [$c3]))
                ->when($ile, fn ($q) => $q->where('territoire', $ile))
                ->latest('id')
                ->limit(1000)
                ->pluck('price')
                ->map(fn ($p) => (float) $p)
                ->sort()
                ->values()
                ->all();
        });
    }

    /** Quartile par interpolation linéaire, arrondi à l'euro (au moins 1 €). */
    private static function quartile(array $tries, float $q): int
    {
        $position = (count($tries) - 1) * $q;
        $bas = (int) floor($position);
        $haut = (int) ceil($position);
        $valeur = $tries[$bas] + ($tries[$haut] - $tries[$bas]) * ($position - $bas);

        return max(1, (int) round($valeur));
    }
}
