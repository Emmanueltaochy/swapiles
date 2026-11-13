<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\PushEnvoi;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pourquoi une notification arrive plusieurs fois ? Chiffres anonymes
 * uniquement (journal de déploiement public) : derniers envois manuels,
 * jetons des comptes admin, jetons multiples par membre, tâches relancées.
 */
class DiagnosticPushDoublons extends Command
{
    protected $signature = 'push:doublons';

    protected $description = 'Diagnostic anonyme des notifications reçues en double';

    public function handle(): int
    {
        $this->line('Derniers envois manuels :');
        foreach (PushEnvoi::where('origine', 'manuel')->latest('id')->limit(4)->get() as $e) {
            $this->line(sprintf('  #%d %s · %s · %d appareil(s), %d ok · envoyé %s · fini %s',
                $e->id, $e->created_at?->format('d/m H:i:s'), $e->statut, $e->appareils, $e->appareils_ok,
                $e->envoye_at?->format('H:i:s') ?? '—', $e->updated_at?->format('H:i:s')));
        }

        if ($dernier = PushEnvoi::where('origine', 'manuel')->latest('id')->first()) {
            $this->line('  téléphones touchés depuis le dernier envoi manuel : ' . DeviceToken::where('last_sent_at', '>=', $dernier->created_at)->count()
                . ' / ' . DeviceToken::count());
        }

        $adminIds = User::all(['id', 'email', 'is_banned'])->filter(fn (User $u) => $u->isAdmin())->pluck('id');
        $this->line('Jetons des comptes admin : ' . DeviceToken::whereIn('user_id', $adminIds)->count());
        foreach (DeviceToken::whereIn('user_id', $adminIds)->orderBy('id')->get() as $i => $d) {
            $this->line(sprintf('  jeton %d · %s · créé %s · revu %s · dernier envoi %s (%s)',
                $i + 1, $d->platform ?? '?', $d->created_at?->format('d/m/Y'), $d->last_seen_at?->format('d/m/Y H:i') ?? '—',
                $d->last_sent_at?->format('d/m H:i:s') ?? '—', $d->last_result ?? '—'));
        }

        $multi = DeviceToken::whereNotNull('user_id')->select('user_id', 'platform', DB::raw('COUNT(*) n'))
            ->groupBy('user_id', 'platform')->having('n', '>', 1)->get();
        $this->line('Membres avec plusieurs jetons sur la même plateforme : ' . $multi->count() . ' (jetons en trop : ' . $multi->sum(fn ($r) => $r->n - 1) . ')');
        $this->line('Jetons sans compte : ' . DeviceToken::whereNull('user_id')->count() . ' · total : ' . DeviceToken::count());

        if (Schema::hasTable('failed_jobs')) {
            $echecs = DB::table('failed_jobs')->where('payload', 'like', '%SendPushBroadcast%')->where('failed_at', '>=', now()->subDays(2))->get(['failed_at', 'exception']);
            $this->line('Envois push en échec (48 h) : ' . $echecs->count());
            foreach ($echecs->take(3) as $f) {
                $this->line('  ' . $f->failed_at . ' · ' . mb_substr(strtok((string) $f->exception, "\n"), 0, 120));
            }
        }
        if (Schema::hasTable('jobs')) {
            $this->line('Envois push encore en file : ' . DB::table('jobs')->where('payload', 'like', '%SendPushBroadcast%')->count()
                . ' (relancés : ' . DB::table('jobs')->where('payload', 'like', '%SendPushBroadcast%')->where('attempts', '>', 1)->count() . ')');
        }
        $this->line('Délai avant relance d’une tâche : ' . config('queue.connections.' . config('queue.default') . '.retry_after', '?') . ' s (file : ' . config('queue.default') . ')');

        return self::SUCCESS;
    }
}
