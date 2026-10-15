<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * « Annonces populaires » de l'accueil.
 *
 * - Popularité RÉCENTE : vues et favoris des 7 derniers jours, et non le
 *   compteur de vues depuis toujours (qui gardait les mêmes annonces en tête).
 * - Rotation : on tire la sélection parmi les meilleures candidates, les mieux
 *   placées ayant plus de chances. Le tirage change toutes les quelques heures
 *   et d'un visiteur à l'autre, mais reste stable pendant une visite.
 * - Variété : au plus 2 annonces par vendeur, jamais les siennes.
 */
class Tendances
{
    public const JOURS = 7;

    public const CANDIDATES = 24;

    public const PAR_VENDEUR = 2;

    public const HEURES_ROTATION = 3;

    /** Une vue de plus aujourd'hui pour cette annonce. */
    public static function noterVue(Listing $listing): void
    {
        try {
            DB::table('listing_vues_jours')->upsert(
                [['listing_id' => $listing->id, 'jour' => now()->toDateString(), 'vues' => 1]],
                ['listing_id', 'jour'],
                ['vues' => DB::raw('vues + 1')]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Graine du tirage : même visiteur + même créneau de 3 h = même sélection. */
    public static function graine(string $visiteur): string
    {
        return $visiteur . '|' . now()->toDateString() . '|' . intdiv((int) now()->format('G'), self::HEURES_ROTATION);
    }

    /**
     * @param  callable  $territoire  filtre d'île appliqué à la requête
     */
    public static function populaires(callable $territoire, ?User $moi, string $graine, int $nombre = 8): Collection
    {
        $depuis = now()->subDays(self::JOURS - 1)->toDateString();

        $candidates = Listing::query()
            ->with(['images', 'user'])->withCount(['favorisRecus as favorited_by_count'])
            ->where('status', 'published')
            ->where($territoire)
            ->when($moi, fn ($query) => $query->where('user_id', '!=', $moi->id))
            ->select('listings.*')
            ->selectSub(
                DB::table('listing_vues_jours')
                    ->selectRaw('COALESCE(SUM(vues), 0)')
                    ->whereColumn('listing_vues_jours.listing_id', 'listings.id')
                    ->where('jour', '>=', $depuis),
                'vues_recentes'
            )
            ->selectSub(
                DB::table('favorites')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('favorites.listing_id', 'listings.id')
                    ->whereColumn('favorites.user_id', '!=', 'listings.user_id')
                    ->where('favorites.created_at', '>=', $depuis),
                'favoris_recents'
            )
            ->orderByRaw('(vues_recentes + 3 * favoris_recents) DESC')
            ->orderByDesc('views_count')
            ->latest()
            ->take(self::CANDIDATES)
            ->get();

        return self::tirer($candidates, $graine, $nombre);
    }

    /**
     * Tirage pondéré sans remise : la 1re candidate a plus de chances que la
     * 24e, mais toutes peuvent sortir.
     */
    public static function tirer(Collection $candidates, string $graine, int $nombre): Collection
    {
        $hasard = new Randomizer(new Mt19937(crc32($graine)));
        $reste = $candidates->values()->all();
        $choisies = [];
        $parVendeur = [];

        while (count($choisies) < $nombre && $reste !== []) {
            // Poids : la popularité récente, plus un socle selon le rang pour
            // que les nouvelles annonces sans vues aient aussi leur chance.
            $poids = [];
            foreach ($reste as $rang => $annonce) {
                $score = (int) ($annonce->vues_recentes ?? 0) + 3 * (int) ($annonce->favoris_recents ?? 0);
                $poids[$rang] = $score + max(1, self::CANDIDATES - $rang);
            }

            $cible = $hasard->getInt(1, max(1, array_sum($poids)));
            foreach ($poids as $rang => $p) {
                $cible -= $p;
                if ($cible <= 0) {
                    break;
                }
            }

            $annonce = $reste[$rang];
            array_splice($reste, $rang, 1);

            if (($parVendeur[$annonce->user_id] ?? 0) >= self::PAR_VENDEUR) {
                continue;
            }
            $parVendeur[$annonce->user_id] = ($parVendeur[$annonce->user_id] ?? 0) + 1;
            $choisies[] = $annonce;
        }

        return collect($choisies);
    }

    /** On ne garde que 30 jours d'historique. */
    public static function nettoyer(): int
    {
        return DB::table('listing_vues_jours')->where('jour', '<', now()->subDays(30)->toDateString())->delete();
    }
}
