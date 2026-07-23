<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Îles supplémentaires où une annonce est disponible (en plus de son île
 * principale). Réservé aux annonces avec Colissimo activé : une remise en main
 * propre est impossible entre deux îles.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('listings', 'also_territoires')) {
            return;
        }

        Schema::table('listings', function (Blueprint $table) {
            $table->json('also_territoires')->nullable()->after('territoire');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('listings', 'also_territoires')) {
            Schema::table('listings', function (Blueprint $table) {
                $table->dropColumn('also_territoires');
            });
        }
    }
};
