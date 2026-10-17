<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La recherche cherche maintenant mot par mot : « jardin plante » trouve les
 * plantes rangées dans Jardin. On retire des « introuvables » les termes
 * qui trouvent désormais des annonces.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('search_no_results')) {
            return;
        }

        foreach (DB::table('search_no_results')->distinct()->pluck('term') as $terme) {
            if (\App\Support\SearchLogger::existeSurLaPlateforme((string) $terme)) {
                DB::table('search_no_results')->where('term', $terme)->delete();
            }
        }
    }

    public function down(): void
    {
        // Rien à restaurer.
    }
};
