<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Diagnostic des bordereaux Colissimo pour les journaux de déploiement
 * (dépôt public) : derniers échecs avec le code et le message de Colissimo,
 * sans nom, adresse, téléphone ni e-mail.
 */
class DiagnosticColissimo extends Command
{
    protected $signature = 'colissimo:diagnostic {--jours=10}';

    protected $description = 'Derniers échecs de génération de bordereau Colissimo (anonymes)';

    public function handle(): int
    {
        $this->line('Identifiants Colissimo : ' . (filled(env('COLISSIMO_ACCOUNT_NUMBER') ?: env('COLISSIMO_CONTRACT_NUMBER')) && filled(env('COLISSIMO_PASSWORD')) ? 'configurés' : 'MANQUANTS'));

        $depuis = now()->subDays((int) $this->option('jours'))->format('Y-m-d');
        $cas = [];
        foreach (array_slice(glob(storage_path('logs/*.log')) ?: [], -3) as $fichier) {
            $f = @fopen($fichier, 'r');
            if (! $f) {
                continue;
            }
            while (($l = fgets($f)) !== false) {
                if (! str_contains($l, 'Erreur génération bordereau Colissimo') || ! preg_match('/^\[(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})/', $l, $d) || $d[1] < $depuis) {
                    continue;
                }
                $ctx = json_decode((string) substr($l, (int) strpos($l, '{')), true) ?: [];
                $cas[] = ['quand' => $d[1] . ' ' . $d[2], 'vente' => $ctx['transaction_id'] ?? '?', 'raison' => self::raison((string) ($ctx['message'] ?? ''))];
            }
            fclose($f);
        }

        if ($cas === []) {
            $this->line('Aucun échec de bordereau ces ' . $this->option('jours') . ' derniers jours.');

            return self::SUCCESS;
        }
        $this->line(count($cas) . ' échec(s) de bordereau ces ' . $this->option('jours') . ' derniers jours :');
        foreach (array_slice(array_reverse($cas), 0, 15) as $c) {
            $this->line('  ' . $c['quand'] . ' | vente n°' . $c['vente'] . ' | ' . $c['raison']);
        }

        return self::SUCCESS;
    }

    /** Code(s) et message(s) Colissimo, sans aucune donnée personnelle. */
    public static function raison(string $message): string
    {
        $codes = [];
        // Chaque message Colissimo est un petit objet {"id":…, "messageContent":…} (ordre des champs variable).
        if (preg_match_all('/\{[^{}]*"messageContent"[^{}]*\}/u', $message, $objets)) {
            foreach ($objets[0] as $o) {
                $id = preg_match('/"id"\s*:\s*"?(\d+)"?/', $o, $a) ? $a[1] : '?';
                $texte = preg_match('/"messageContent"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $o, $b) ? (json_decode('"' . $b[1] . '"') ?? $b[1]) : '';
                if ($id !== '0') {
                    $codes[] = $id . ' ' . self::nettoyer((string) $texte);
                }
            }
        }
        if ($codes) {
            return 'Colissimo : ' . implode(' / ', array_unique($codes));
        }
        // Messages du site (adresse manquante, identifiants…) ou HTTP sans détail.
        $court = preg_replace('/\s*:\s*[{\[].*$/s', '', $message);

        return self::nettoyer(mb_substr((string) $court, 0, 160));
    }

    private static function nettoyer(string $t): string
    {
        $t = preg_replace('/[\w.+-]+@[\w-]+\.[\w.]+/u', '[e-mail]', $t);
        $t = preg_replace('/\+?\d[\d .]{7,}\d/u', '[numéro]', $t);

        return trim((string) $t);
    }
}
