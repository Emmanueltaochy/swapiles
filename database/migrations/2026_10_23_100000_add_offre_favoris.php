<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offre aux favoris : le vendeur propose une réduction, valable 48 h, à
 * toutes les personnes qui ont mis son article en favori. Chaque offre est
 * une offre acceptée d'avance (le paiement utilise déjà ce mécanisme).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_offers', function (Blueprint $table) {
            $table->timestamp('expire_at')->nullable();
            $table->string('source', 20)->nullable();
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('offre_favoris_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            // Dernier récap « Nouveautés de vos dressings » envoyé.
            $table->timestamp('nouveautes_envoyees_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('listing_offers', function (Blueprint $table) {
            $table->dropColumn(['expire_at', 'source']);
        });
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('offre_favoris_at');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nouveautes_envoyees_at');
        });
    }
};
