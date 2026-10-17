<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Île de l'installation (choix d'île du téléphone, ou île du profil si le
 * membre est connecté). Sert uniquement aux totaux par île : toujours aucun
 * lien avec le compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_installations', function (Blueprint $table) {
            $table->string('territoire', 40)->nullable()->index()->after('platform');
        });
    }

    public function down(): void
    {
        Schema::table('app_installations', function (Blueprint $table) {
            $table->dropIndex(['territoire']);
            $table->dropColumn('territoire');
        });
    }
};
