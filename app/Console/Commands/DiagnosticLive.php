<?php

namespace App\Console\Commands;

use App\Models\LiveSession;
use App\Support\LiveEnregistrement;
use App\Support\LiveKit;
use Illuminate\Console\Command;

/**
 * Diagnostic des lives pour les journaux de déploiement (dépôt public) :
 * réglages présents ou non (jamais leur valeur), état des derniers lives, et
 * codes d'échec du service d'enregistrement (statut HTTP + code technique).
 */
class DiagnosticLive extends Command
{
    protected $signature = 'live:diagnostic';

    protected $description = 'Diagnostic anonyme des lives et de leur enregistrement (replay)';

    public function handle(): int
    {
        $oui = fn ($v) => filled($v) ? 'oui' : 'NON';
        $r = config('live.replay');

        $this->line('Lives ouverts à tous : ' . (config('live.ouvert_a_tous') ? 'OUI (tout le monde peut regarder et animer)' : 'non (admins et testeurs seulement)')
            . ' | testeurs listés : ' . count((array) config('live.testeurs', [])));
        $this->line('Service vidéo (LiveKit) configuré : ' . (LiveKit::configure() ? 'oui' : 'NON'));
        $this->line('Enregistrement des replays configuré : ' . (LiveEnregistrement::configure() ? 'oui' : 'NON')
            . ' (clé ' . $oui($r['cle'] ?? null) . ', secret ' . $oui($r['secret'] ?? null) . ', espace ' . $oui($r['bucket'] ?? null)
            . ', adresse ' . $oui($r['endpoint'] ?? null) . ', adresse publique ' . $oui($r['url_publique'] ?? null) . ')');

        LiveSession::query()->latest('id')->limit(3)->get()->each(function (LiveSession $l) {
            $this->line('Live n°' . $l->id . ' : ' . $l->statut
                . ' | enregistrement lancé : ' . ($l->enregistrement_id ? 'oui' : 'non')
                . ' | replay : ' . ($l->replay_supprime_at ? 'supprimé' : ($l->replay_publie_at ? 'publié' : ($l->replay_chemin ? 'en attente du choix' : 'aucun'))));
        });

        $dernier = \App\Support\CarnetDiagnostic::dernier('live-enregistrement');
        $this->line('Dernière demande d’enregistrement : ' . ($dernier
            ? $dernier['quand'] . ' | ' . $dernier['etape'] . ' → ' . $dernier['resultat']
                . ($dernier['statut'] ? ' | HTTP ' . $dernier['statut'] : '') . ($dernier['code'] ? ' | code ' . $dernier['code'] : '')
            : 'aucune depuis la mise en place du suivi (la page du live ne l’a pas demandée)'));

        // Échecs notés par LiveEnregistrement::appeler (statut et code seulement).
        $echecs = [];
        foreach (array_slice(glob(storage_path('logs/*.log')) ?: [], -3) as $fichier) {
            foreach (new \SplFileObject($fichier) as $ligne) {
                if (! is_string($ligne) || ! str_contains($ligne, 'Live : ')) {
                    continue;
                }
                if (! preg_match('/Live : (échec|.+ injoignable) ?(\w+)?/u', $ligne, $m)) {
                    continue;
                }
                $statut = preg_match('/"statut":(\d+)/', $ligne, $s) ? $s[1] : '-';
                $code = preg_match('/\\\\?"code\\\\?":\\\\?"([a-z_]+)/', $ligne, $c) ? $c[1] : '-';
                $cle = trim($m[0]) . ' | HTTP ' . $statut . ' | code ' . $code;
                $echecs[$cle] = ($echecs[$cle] ?? 0) + 1;
            }
        }
        if ($echecs === []) {
            $this->line('Aucun échec du service d’enregistrement dans les journaux récents.');
        }
        foreach ($echecs as $cle => $n) {
            $this->line($n . ' fois : ' . $cle);
        }

        return self::SUCCESS;
    }
}
