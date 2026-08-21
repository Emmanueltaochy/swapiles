<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coordonnées d'un point relais, pour l'affichage sur la carte au moment du
 * choix par l'acheteur. Facultatives : si vides, on retombe sur le centre de
 * la commune (DomTomGeo). Renseignées, elles placent le pin sur la boutique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relay_points', function (Blueprint $table) {
            if (! Schema::hasColumn('relay_points', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('city');
            }
            if (! Schema::hasColumn('relay_points', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('relay_points', function (Blueprint $table) {
            foreach (['latitude', 'longitude'] as $col) {
                if (Schema::hasColumn('relay_points', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
