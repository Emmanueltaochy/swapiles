<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * « Recommandé pour vous ».
 *
 * On part de ce que le membre a VRAIMENT fait ces dernières semaines : les
 * articles qu'il a ouverts et ceux qu'il a mis en favori (un favori compte
 * trois fois plus qu'une simple visite). On en tire ses goûts — catégories,
 * marques, tailles, gamme de prix — puis on cherche, sur son île, des
 * articles récents qui leur ressemblent et qu'il n'a encore jamais vus.
 */
class Recommandations
{
    private const POIDS_FAVORI = 3.0;

    private const POIDS_VISITE = 1.0;

    /** Marques qui n'en sont pas : elles ne disent rien des goûts. */
    private const MARQUES_VIDES = ['', '-', 'autre', 'autres', 'aucune', 'sans marque', 'sans', 'non', 'marque inconnue', 'inconnue', 'no name'];

    /**
     * Note qu'un membre a ouvert un article (appelé à chaque vraie visite,
     * jamais pour un préchargement).
     */
    public static function noterConsultation(?User $membre, Listing $listing): void
    {
        if (! $membre || $membre->id === $listing->user_id) {
            return;
        }

        try {
            DB::table('listing_consultations')->upsert(
                [[
                    'user_id' => $membre->id,
                    'listing_id' => $listing->id,
                    'vues' => 1,
                    'derniere_vue_at' => now(),
                ]],
                ['user_id', 'listing_id'],
                ['vues' => DB::raw('vues + 1'), 'derniere_vue_at' => now()]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Les goûts du membre, déduits de son historique récent.
     *
     * @return array{poids: float, categories: array<string, float>, sous_types: array<string, float>, marques: array<string, float>, tailles: array<string, float>, prix: ?array{0: float, 1: float}}
     */
    public static function profil(User $membre): array
    {
        $depuis = now()->subDays((int) config('recommandations.jours_signaux', 45));
        $poidsParAnnonce = [];

        DB::table('favorites')
            ->where('user_id', $membre->id)
            ->where('created_at', '>=', $depuis)
            ->pluck('listing_id')
            ->each(function ($id) use (&$poidsParAnnonce) {
                $poidsParAnnonce[$id] = ($poidsParAnnonce[$id] ?? 0) + self::POIDS_FAVORI;
            });

        if (Schema::hasTable('listing_consultations')) {
            DB::table('listing_consultations')
                ->where('user_id', $membre->id)
                ->where('derniere_vue_at', '>=', $depuis)
                ->pluck('vues', 'listing_id')
                ->each(function ($vues, $id) use (&$poidsParAnnonce) {
                    // Revenir sur un article dit qu'il plaît : jusqu'à +1.
                    $poidsParAnnonce[$id] = ($poidsParAnnonce[$id] ?? 0)
                        + self::POIDS_VISITE + 0.5 * min(max((int) $vues - 1, 0), 2);
                });
        }

        $profil = ['poids' => 0.0, 'categories' => [], 'sous_types' => [], 'marques' => [], 'tailles' => [], 'prix' => null];

        if ($poidsParAnnonce === []) {
            return $profil;
        }

        $annonces = Listing::query()
            ->whereIn('id', array_keys($poidsParAnnonce))
            ->where('user_id', '!=', $membre->id)
            ->get(['id', 'category_level1', 'category_level2', 'category_level3', 'marque', 'taille', 'price']);

        $prix = [];

        foreach ($annonces as $annonce) {
            $poids = $poidsParAnnonce[$annonce->id];
            $profil['poids'] += $poids;

            if ($annonce->category_level1) {
                $cle = self::cleCategorie($annonce->category_level1, $annonce->category_level2);
                $profil['categories'][$cle] = ($profil['categories'][$cle] ?? 0) + $poids;

                if ($annonce->category_level2 && $annonce->category_level3) {
                    $type = $cle . '|' . $annonce->category_level3;
                    $profil['sous_types'][$type] = ($profil['sous_types'][$type] ?? 0) + $poids;
                }
            }

            if (($marque = self::normaliser($annonce->marque)) !== null && ! in_array($marque, self::MARQUES_VIDES, true)) {
                $profil['marques'][$marque] = ($profil['marques'][$marque] ?? 0) + $poids;
            }

            if (($taille = self::normaliser($annonce->taille)) !== null) {
                $profil['tailles'][$taille] = ($profil['tailles'][$taille] ?? 0) + $poids;
            }

            if ((float) $annonce->price > 0) {
                $prix[] = (float) $annonce->price;
            }
        }

        if ($prix !== []) {
            sort($prix);
            $median = $prix[intdiv(count($prix), 2)];
            // Une gamme large : on reste dans le budget, sans être trop strict.
            $profil['prix'] = [round($median * 0.4, 2), round($median * 2.5, 2)];
        }

        arsort($profil['categories']);
        arsort($profil['sous_types']);
        arsort($profil['marques']);
        arsort($profil['tailles']);

        return $profil;
    }

    /**
     * Articles recommandés au membre, du plus pertinent au moins pertinent.
     * Chaque article porte sa note dans l'attribut « score_reco ».
     *
     * @return Collection<int, Listing>
     */
    public static function pour(User $membre, int $limite = 12): Collection
    {
        $profil = self::profil($membre);

        if ($profil['poids'] < (float) config('recommandations.signal_minimum', 3) || $profil['categories'] === []) {
            return collect();
        }

        // Les 4 catégories préférées suffisent : au-delà, ce n'est plus un goût.
        $categories = array_slice($profil['categories'], 0, 4, true);

        $requete = Listing::query()
            ->with(['images', 'user'])
            ->withCount('favoritedBy')
            ->where('status', 'published')
            ->where('user_id', '!=', $membre->id)
            ->whereHas('images')
            ->where('created_at', '>=', now()->subDays((int) config('recommandations.fraicheur_jours', 30)))
            // Déjà en favori, déjà ouvert ou déjà proposé : rien de nouveau.
            ->whereNotIn('id', fn ($q) => $q->select('listing_id')->from('favorites')->where('user_id', $membre->id))
            ->whereNotIn('id', fn ($q) => $q->select('listing_id')->from('listing_consultations')->where('user_id', $membre->id))
            ->whereNotIn('id', fn ($q) => $q->select('listing_id')->from('recommandations')->where('user_id', $membre->id))
            // Jamais un membre bloqué (dans un sens ou dans l'autre), ni un compte banni.
            ->whereNotIn('user_id', fn ($q) => $q->select('blocked_id')->from('user_blocks')->where('blocker_id', $membre->id))
            ->whereNotIn('user_id', fn ($q) => $q->select('blocker_id')->from('user_blocks')->where('blocked_id', $membre->id))
            ->whereNotIn('user_id', fn ($q) => $q->select('id')->from('users')->where('is_banned', true))
            ->where(function ($q) use ($categories) {
                foreach (array_keys($categories) as $cle) {
                    [$n1, $n2] = explode('|', $cle);
                    $q->orWhere(function ($q2) use ($n1, $n2) {
                        $q2->where('category_level1', $n1);
                        if ($n2 !== '*') {
                            $q2->where('category_level2', $n2);
                        }
                    });
                }
            });

        // Sur l'île du membre : ce qu'il peut acheter sans frais d'avion.
        if ($membre->territoire) {
            $territoire = $membre->territoire;
            $requete->where(function ($q) use ($territoire) {
                $q->where('territoire', $territoire);
                if (Schema::hasColumn('listings', 'also_territoires')) {
                    $q->orWhere('also_territoires', 'like', '%"' . $territoire . '"%');
                }
            });
        }

        $candidats = $requete->latest()->limit(400)->get();

        $maxCategorie = max($categories);
        $maxSousType = $profil['sous_types'] ? max($profil['sous_types']) : 1;
        $maxMarque = $profil['marques'] ? max($profil['marques']) : 1;
        $fraicheur = max(1, (int) config('recommandations.fraicheur_jours', 30));

        foreach ($candidats as $annonce) {
            $cle = self::cleCategorie($annonce->category_level1, $annonce->category_level2);
            $affinite = $categories[$cle] ?? $categories[self::cleCategorie($annonce->category_level1, null)] ?? 0;

            $score = 3 * ($affinite / $maxCategorie);

            if ($annonce->category_level3 && isset($profil['sous_types'][$cle . '|' . $annonce->category_level3])) {
                $score += 2 * ($profil['sous_types'][$cle . '|' . $annonce->category_level3] / $maxSousType);
            }

            $marque = self::normaliser($annonce->marque);
            if ($marque !== null && isset($profil['marques'][$marque])) {
                $score += 1.5 + 0.5 * ($profil['marques'][$marque] / $maxMarque);
            }

            $taille = self::normaliser($annonce->taille);
            if ($taille !== null && isset($profil['tailles'][$taille])) {
                $score += 1.5;
            }

            if ($profil['prix'] && (float) $annonce->price >= $profil['prix'][0] && (float) $annonce->price <= $profil['prix'][1]) {
                $score += 1;
            }

            $age = max(0, $annonce->created_at?->diffInDays(now()) ?? $fraicheur);
            $score += max(0, 1 - $age / $fraicheur);

            $annonce->setAttribute('score_reco', round($score, 2));
        }

        $seuil = (float) config('recommandations.score_minimum', 3.0);
        $parVendeur = (int) config('recommandations.max_par_vendeur', 2);
        $compteVendeurs = [];

        return $candidats
            ->filter(fn ($a) => $a->score_reco >= $seuil)
            ->sortByDesc('score_reco')
            // Variété : pas toute la sélection chez le même vendeur.
            ->filter(function ($a) use (&$compteVendeurs, $parVendeur) {
                $compteVendeurs[$a->user_id] = ($compteVendeurs[$a->user_id] ?? 0) + 1;

                return $compteVendeurs[$a->user_id] <= $parVendeur;
            })
            ->take($limite)
            ->values();
    }

    private static function cleCategorie(?string $n1, ?string $n2): string
    {
        return $n1 . '|' . ($n2 ?: '*');
    }

    private static function normaliser(?string $valeur): ?string
    {
        $valeur = trim(mb_strtolower((string) $valeur));

        return $valeur === '' ? null : $valeur;
    }
}
