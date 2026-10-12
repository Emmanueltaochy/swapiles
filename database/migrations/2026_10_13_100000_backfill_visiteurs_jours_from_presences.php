<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le compteur « visiteurs uniques par jour » n'a été mis en ligne qu'en fin
 * de journée, alors que les présences humaines étaient déjà enregistrées
 * depuis le matin. On rattrape : chaque présence compte pour le jour de son
 * arrivée et pour le jour de sa dernière activité.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('presences')->orderBy('id')->chunk(500, function ($presences) {
            $lignes = [];
            foreach ($presences as $p) {
                foreach (array_unique([substr((string) $p->debut_at, 0, 10), substr((string) $p->last_seen_at, 0, 10)]) as $jour) {
                    $lignes[] = [
                        'jour' => $jour,
                        'cle' => $p->cle,
                        'user_id' => $p->user_id,
                        'appli' => $p->appli,
                        'source' => $p->source,
                        'territoire' => $p->territoire,
                    ];
                }
            }
            DB::table('visiteurs_jours')->insertOrIgnore($lignes);
        });
    }

    public function down(): void
    {
        // Rien : les lignes rattrapées sont des visites réelles.
    }
};
