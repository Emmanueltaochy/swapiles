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

        return self::SUCCESS;
    }
}
