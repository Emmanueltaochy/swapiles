<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Même budget IA pour le remplissage d'annonces et la recherche par photo : on distingue les deux. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_depot_usages', function (Blueprint $table) {
            $table->string('fonction', 20)->default('depot')->index()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('ia_depot_usages', function (Blueprint $table) {
            $table->dropIndex(['fonction']);
            $table->dropColumn('fonction');
        });
    }
};
