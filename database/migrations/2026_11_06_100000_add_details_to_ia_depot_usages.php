<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Détail de chaque utilisation de l'IA pour l'admin : le formulaire de dépôt
 * (relié à l'annonce publiée par son jeton) et, pour la recherche par photo,
 * la recherche obtenue. Les photos elles-mêmes ne sont jamais conservées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_depot_usages', function (Blueprint $table) {
            $table->string('jeton_depot', 64)->nullable()->index()->after('fonction');
            $table->string('resultat', 255)->nullable()->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('ia_depot_usages', function (Blueprint $table) {
            $table->dropIndex(['jeton_depot']);
            $table->dropColumn(['jeton_depot', 'resultat']);
        });
    }
};
