<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\DomTomGeo;
use Illuminate\Console\Command;

class SyncTerritoireFromPostal extends Command
{
    protected $signature = 'users:sync-territoire-from-postal
        {--dry-run : Affiche les comptes à corriger sans rien modifier}';

    protected $description = 'Aligne le territoire des membres sur leur code postal DOM-TOM (971 = Guadeloupe, 972 = Martinique, 973 = Guyane, 974 = La Réunion, 976 = Mayotte) lorsqu\'il y a incohérence.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $fixed = 0;
        $checked = 0;

        User::query()
            ->whereNotNull('postal_code')
            ->where('postal_code', '!=', '')
            ->chunkById(500, function ($users) use (&$fixed, &$checked, $dryRun) {
                foreach ($users as $user) {
                    $checked++;

                    $target = DomTomGeo::territoireFromPostal($user->postal_code);

                    // On ne touche que si le code postal correspond à une de nos îles
                    // ET que le territoire actuel est différent.
                    if (! $target || $user->territoire === $target) {
                        continue;
                    }

                    $this->line("→ #{$user->id} {$user->email} : « " . ($user->territoire ?? '—') . " » → « {$target} » (CP {$user->postal_code})");

                    if (! $dryRun) {
                        $user->forceFill(['territoire' => $target])->save();
                    }

                    $fixed++;
                }
            });

        $this->info($dryRun
            ? "Comptes vérifiés : {$checked} · à corriger : {$fixed}"
            : "Comptes vérifiés : {$checked} · territoires corrigés : {$fixed}");

        return self::SUCCESS;
    }
}
