<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckColissimoCredentials extends Command
{
    protected $signature = 'swapiles:colissimo-check';

    protected $description = "Vérifie (masqué) que les identifiants Colissimo sont bien chargés depuis le .env.";

    public function handle(): int
    {
        $vars = [
            'COLISSIMO_ACCOUNT_NUMBER',
            'COLISSIMO_CONTRACT_NUMBER',
            'COLISSIMO_PASSWORD',
        ];

        foreach ($vars as $name) {
            $value = (string) env($name);
            $trimmed = trim($value);

            if ($trimmed === '') {
                $this->warn("$name : VIDE / non défini");
                continue;
            }

            $len = mb_strlen($trimmed);
            $masked = mb_substr($trimmed, 0, 1) . str_repeat('•', max(0, $len - 2)) . mb_substr($trimmed, -1);
            $spaceWarning = $value !== $trimmed ? '  ⚠️ espaces autour de la valeur !' : '';

            $this->info("$name : défini (longueur $len) → {$masked}{$spaceWarning}");
        }

        $effective = trim((string) (env('COLISSIMO_ACCOUNT_NUMBER') ?: env('COLISSIMO_CONTRACT_NUMBER')));
        $this->line('');
        $this->line('Numéro de contrat effectivement utilisé : ' . ($effective !== '' ? 'OK' : 'AUCUN (les deux sont vides)'));

        return self::SUCCESS;
    }
}
