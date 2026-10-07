<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Support\PushPolicy;
use App\Support\Recommandations;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * « Recommandé pour vous » — envoyé de temps en temps, pas tout le temps.
 *
 * Lancée chaque heure, la commande ne prévient un membre que si :
 *  - il est entre 18 h et 21 h sur SON île (le moment où l'on fait défiler) ;
 *  - son dernier envoi date d'au moins 4 jours ;
 *  - il a assez regardé ou aimé d'articles pour qu'on connaisse ses goûts ;
 *  - au moins deux articles récents y correspondent vraiment ;
 *  - il n'a pas coupé ces suggestions dans ses réglages.
 * Sinon, rien ne part. Les réglages sont dans config/recommandations.php.
 */
class SendRecommendations extends Command
{
    protected $signature = 'recommandations:envoyer
        {--force : Ignore le créneau du soir (tests manuels)}
        {--dry-run : Affiche sans rien envoyer}';

    protected $description = 'Envoie de temps en temps une sélection « Recommandé pour vous » d’après ce que chaque membre regarde et met en favori';

    public function handle(): int
    {
        $this->menage();

        $depuis = now()->subDays((int) config('recommandations.jours_signaux', 45));
        $intervalle = (int) config('recommandations.intervalle_jours', 4);
        $parEnvoi = (int) config('recommandations.articles_par_envoi', 12);
        $minimum = (int) config('recommandations.minimum_articles', 2);
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $envoyes = 0;

        User::query()
            ->where(fn ($q) => $q->whereNull('is_banned')->orWhere('is_banned', false))
            ->where(fn ($q) => $q->whereNull('recommandations_envoyees_at')
                ->orWhere('recommandations_envoyees_at', '<=', now()->subDays($intervalle)))
            ->where(function ($q) use ($depuis) {
                // Seulement les membres actifs récemment : les autres n'ont
                // pas d'historique, et on ne relance pas un compte endormi.
                $q->whereIn('id', fn ($s) => $s->select('user_id')->from('listing_consultations')->where('derniere_vue_at', '>=', $depuis))
                    ->orWhereIn('id', fn ($s) => $s->select('user_id')->from('favorites')->where('created_at', '>=', $depuis));
            })
            ->chunkById(200, function ($membres) use ($force, $dryRun, $parEnvoi, $minimum, &$envoyes) {
                foreach ($membres as $membre) {
                    if (! $force && ! $this->dansLeCreneau($membre)) {
                        continue;
                    }

                    if (! $membre->accepteNotification('recommandations', 'push')) {
                        continue;
                    }

                    // Sélection trop maigre ce soir : on ne recalcule pas à
                    // chaque heure, on retentera demain.
                    $cleVide = 'reco_vide:' . $membre->id . ':' . now()->toDateString();
                    if (! $force && Cache::has($cleVide)) {
                        continue;
                    }

                    $articles = Recommandations::pour($membre, $parEnvoi);

                    if ($articles->count() < $minimum) {
                        Cache::put($cleVide, 1, now()->addDay());

                        continue;
                    }

                    if ($dryRun) {
                        $this->line("→ [dry-run] {$membre->email} · {$articles->count()} articles · " . $articles->first()->title);
                        $envoyes++;

                        continue;
                    }

                    $this->envoyer($membre, $articles);
                    $envoyes++;
                }
            });

        $this->info("Sélections envoyées : {$envoyes}");

        return self::SUCCESS;
    }

    /** Entre 18 h et 21 h, heure de l'île du membre. */
    private function dansLeCreneau(User $membre): bool
    {
        $heure = (int) Carbon::now()->setTimezone(PushPolicy::fuseau($membre))->format('G');

        return $heure >= (int) config('recommandations.heure_debut', 18)
            && $heure < (int) config('recommandations.heure_fin', 21);
    }

    private function envoyer(User $membre, $articles): void
    {
        DB::transaction(function () use ($membre, $articles) {
            $maintenant = now();

            DB::table('recommandations')->insertOrIgnore($articles->map(fn ($a) => [
                'user_id' => $membre->id,
                'listing_id' => $a->id,
                'score' => $a->score_reco,
                'envoye_at' => $maintenant,
            ])->all());

            $membre->forceFill(['recommandations_envoyees_at' => $maintenant])->save();
        });

        $premier = $articles->first();
        $autres = $articles->count() - 1;
        $prix = (float) $premier->price > 0
            ? ' à ' . number_format((float) $premier->price, fmod((float) $premier->price, 1) == 0 ? 0 : 2, ',', ' ') . ' €'
            : '';

        try {
            Notification::create([
                'user_id' => $membre->id,
                'type' => 'recommandations',
                'title' => 'Sélectionné pour vous ✨',
                'message' => '« ' . $premier->title . ' »' . $prix
                    . ($autres > 0 ? ' et ' . $autres . ' autre' . ($autres > 1 ? 's' : '') . ' article' . ($autres > 1 ? 's' : '') : '')
                    . ' dans le style de ce que vous aimez.',
                'url' => route('account.recommendations', absolute: false),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** L'historique ne sert qu'aux goûts récents : inutile de le garder longtemps. */
    private function menage(): void
    {
        try {
            DB::table('listing_consultations')->where('derniere_vue_at', '<', now()->subDays(120))->delete();
            DB::table('recommandations')->where('envoye_at', '<', now()->subDays(180))->delete();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
