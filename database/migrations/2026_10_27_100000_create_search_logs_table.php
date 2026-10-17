<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Toutes les recherches tapées (pas seulement celles sans résultat), et
 * nettoyage de l'historique « sans résultat » : on n'y garde que ce qui
 * n'existe vraiment pas sur la plateforme.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('search_logs')) {
            Schema::create('search_logs', function (Blueprint $table) {
                $table->id();
                $table->string('term', 120)->index();
                $table->string('raw_term', 255)->nullable();
                $table->unsignedInteger('resultats')->default(0);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('visitor_id', 64)->nullable();
                $table->string('territoire')->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        // « femme », « robe »… : présents sur la plateforme, mais journalisés
        // « sans résultat » parce qu'un filtre (île, catégorie) vidait la liste.
        if (Schema::hasTable('search_no_results')) {
            foreach (DB::table('search_no_results')->distinct()->pluck('term') as $terme) {
                if (\App\Support\SearchLogger::existeSurLaPlateforme((string) $terme)) {
                    DB::table('search_no_results')->where('term', $terme)->delete();
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
