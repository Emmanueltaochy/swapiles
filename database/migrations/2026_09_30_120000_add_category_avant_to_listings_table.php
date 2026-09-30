<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mémoire du rangement automatique des catégories.
 *
 * Le rangement touche des milliers d'annonces d'un coup. On garde donc, pour
 * chaque annonce déplacée, la catégorie qu'elle portait avant : un mauvais
 * rangement se défait alors d'un clic au lieu d'être définitif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->json('category_avant')->nullable()->after('category_level3');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('category_avant');
        });
    }
};
