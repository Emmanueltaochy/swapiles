<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Heure d'arrivée du visiteur dans la journée : permet de tracer les
 * visiteurs humains heure par heure (l'ancienne courbe comptait les robots).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('visiteurs_jours', 'arrivee_at')) {
            Schema::table('visiteurs_jours', function (Blueprint $table) {
                $table->timestamp('arrivee_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('visiteurs_jours', 'arrivee_at')) {
            Schema::table('visiteurs_jours', function (Blueprint $table) {
                $table->dropColumn('arrivee_at');
            });
        }
    }
};
