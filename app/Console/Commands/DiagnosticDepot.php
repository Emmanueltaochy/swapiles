<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Diagnostic du dépôt d'annonce pour les journaux de déploiement (dépôt
 * public) : limites d'envoi du serveur et derniers envois coupés, anonymes.
 */
class DiagnosticDepot extends Command
{
    protected $signature = 'depot:diagnostic';

    protected $description = 'Limites d’envoi du serveur et derniers envois d’annonce coupés (anonymes)';

    public function handle(): int
    {
        $this->line('PHP : post_max_size ' . ini_get('post_max_size') . ' | upload_max_filesize ' . ini_get('upload_max_filesize')
            . ' | max_file_uploads ' . ini_get('max_file_uploads') . ' | max_input_time ' . ini_get('max_input_time'));

        $cas = Cache::get('depot:echecs', []);
        if ($cas === []) {
            $this->line('Aucun envoi d’annonce coupé signalé.');
        }
        foreach (array_slice($cas, 0, 15) as $c) {
            $this->line(sprintf('%s | %s | %d photo(s), %s Mo | envoyé à %d %% | statut %d',
                $c['quand'] ?? '?', $c['appareil'] ?? '?', $c['photos'] ?? 0,
                number_format(($c['poids_ko'] ?? 0) / 1024, 1, ',', ''), $c['avancement'] ?? 0, $c['statut'] ?? 0));
        }

        $photos = Cache::get('depot:photos', []);
        if ($photos === []) {
            $this->line('Choix des photos : aucun compte rendu pour l’instant.');
        } else {
            $soucis = array_filter($photos, fn ($c) => ($c['ajoutees'] ?? 0) < ($c['choisies'] ?? 0) || ($c['erreur'] ?? '') !== '');
            $this->line(sprintf('Choix des photos : %d fois récemment, %d avec au moins une photo non ajoutée.', count($photos), count($soucis)));
            foreach (array_slice($photos, 0, 20) as $c) {
                $this->line(sprintf('%s | %s | choisies %d, ajoutées %d (réduites %d, non lues %d) | illisibles %d, trop lourdes %d, pas des photos %d | types %s | plus grosse %s Mo | %s s%s%s',
                    $c['quand'] ?? '?', $c['appareil'] ?? '?', $c['choisies'] ?? 0, $c['ajoutees'] ?? 0, $c['reduites'] ?? 0, $c['non_lues'] ?? 0,
                    $c['illisibles'] ?? 0, $c['trop_lourdes'] ?? 0, $c['pas_images'] ?? 0, $c['types'] ?? '?',
                    number_format(($c['plus_grosse_ko'] ?? 0) / 1024, 1, ',', ''), number_format(($c['duree_ms'] ?? 0) / 1000, 1, ',', ''),
                    ($c['etapes'] ?? '') !== '' ? ' | étapes ' . $c['etapes'] : '', ($c['erreur'] ?? '') !== '' ? ' | erreur ' . $c['erreur'] : ''));
            }
        }

        return self::SUCCESS;
    }
}
