<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vues et favoris : coupés pour tous les comptes.
 *
 * « Vues » et « favoris » formaient une seule catégorie (« favoris »), activée
 * par défaut. Les membres qui avaient enregistré leurs préférences l'avaient
 * presque tous laissée cochée — c'était la valeur proposée. On retire ce
 * réglage enregistré : tout le monde repart sur les nouveaux défauts (vues et
 * favoris coupés), et chacun les active s'il le souhaite. Les autres réglages
 * enregistrés (messages, dressings suivis, conseils) sont conservés.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('notification_prefs')
            ->orderBy('id')
            ->chunkById(500, function ($membres) {
                foreach ($membres as $membre) {
                    $prefs = json_decode((string) $membre->notification_prefs, true);

                    if (! is_array($prefs) || (! array_key_exists('favoris', $prefs) && ! array_key_exists('vues', $prefs))) {
                        continue;
                    }

                    unset($prefs['favoris'], $prefs['vues']);

                    DB::table('users')->where('id', $membre->id)->update([
                        'notification_prefs' => $prefs === [] ? null : json_encode($prefs),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Rien à restaurer : les anciens réglages ne sont pas conservés.
    }
};
