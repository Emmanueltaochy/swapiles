<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * « 📸 Nouveautés de vos dressings » : chaque soir (18 h chez le membre), un
 * seul récapitulatif des articles publiés par les dressings qu'il suit et
 * qu'il n'a pas encore vus en story. On touche : les stories s'ouvrent.
 *
 * En journée, chaque dressing suivi ne déclenche plus qu'UNE alerte
 * instantanée par jour (voir alerteInstantanee) : le récap du soir fait le reste.
 */
class NouveautesDressings
{
    public const HEURE_LOCALE = 18;

    public const ARTICLES_MIN = 2;

    /** @return array{vendeurs: \Illuminate\Support\Collection, articles: int} */
    public static function pour(User $membre): array
    {
        $depuis = $membre->nouveautes_envoyees_at && $membre->nouveautes_envoyees_at->gt(now()->subDay())
            ? $membre->nouveautes_envoyees_at
            : now()->subDay();

        $suivis = $membre->followedSellers()->pluck('users.id');
        if ($suivis->isEmpty()) {
            return ['vendeurs' => collect(), 'articles' => 0];
        }

        $vus = DB::table('story_vues')->where('user_id', $membre->id)->pluck('vu_jusqu_a', 'vendeur_id');

        $nouveaux = Listing::query()
            ->whereIn('user_id', $suivis)
            ->where('status', 'published')
            ->where('created_at', '>', $depuis)
            ->get(['id', 'user_id', 'created_at'])
            // Déjà vus en story : on n'en reparle pas.
            ->reject(fn ($l) => isset($vus[$l->user_id]) && Carbon::parse($vus[$l->user_id])->gte($l->created_at));

        $vendeurs = User::whereIn('id', $nouveaux->pluck('user_id')->unique())
            ->where(fn ($query) => $query->whereNull('is_banned')->orWhere('is_banned', false))
            ->pluck('name');

        return ['vendeurs' => $vendeurs, 'articles' => $nouveaux->count()];
    }

    /** « Léa, Tom et 2 autres dressings ont publié 7 nouveaux articles. » */
    public static function message(array $nouveautes): string
    {
        $noms = $nouveautes['vendeurs']->values();
        $qui = match (true) {
            $noms->count() === 1 => $noms[0] . ' a',
            $noms->count() === 2 => $noms[0] . ' et ' . $noms[1] . ' ont',
            default => $noms[0] . ', ' . $noms[1] . ' et ' . ($noms->count() - 2) . ' autre' . ($noms->count() > 3 ? 's' : '') . ' dressing' . ($noms->count() > 3 ? 's' : '') . ' ont',
        };

        return $qui . ' publié ' . $nouveautes['articles'] . ' nouveaux articles. Touchez pour les voir en story.';
    }

    /** Toutes les heures : les membres pour qui il est 18 h reçoivent leur récap. */
    public static function envoyer(): int
    {
        $n = 0;
        User::query()
            ->whereIn('id', DB::table('seller_follows')->select('follower_id'))
            ->where(fn ($query) => $query->whereNull('is_banned')->orWhere('is_banned', false))
            ->where(fn ($query) => $query->whereNull('nouveautes_envoyees_at')->orWhere('nouveautes_envoyees_at', '<', now()->subHours(20)))
            ->each(function (User $membre) use (&$n) {
                if ((int) now()->setTimezone(PushPolicy::fuseau($membre))->format('G') !== self::HEURE_LOCALE) {
                    return;
                }

                $nouveautes = self::pour($membre);
                if ($nouveautes['articles'] < self::ARTICLES_MIN) {
                    return;
                }

                Notification::create([
                    'user_id' => $membre->id,
                    'type' => 'dressings_nouveautes',
                    'title' => '📸 Nouveautés de vos dressings',
                    'message' => self::message($nouveautes),
                    'url' => '/?stories=1',
                ]);
                $membre->forceFill(['nouveautes_envoyees_at' => now()])->saveQuietly();
                $n++;
            });

        return $n;
    }

    /**
     * Alerte instantanée « X a publié … » : une seule par dressing suivi et
     * par jour, sinon un vendeur qui publie 10 articles envoie 10 alertes.
     */
    public static function alerteInstantanee(int $abonneId, int $vendeurId): bool
    {
        return Cache::add('alerte_suivi:' . $abonneId . ':' . $vendeurId . ':' . now()->toDateString(), 1, now()->addDay());
    }
}
