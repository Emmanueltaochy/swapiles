<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vente déclarée en main propre à un membre cherché par son nom (pas parmi les
 * personnes qui avaient montré de l'intérêt pour l'annonce) : le vendeur ne
 * peut lui laisser un avis qu'une fois l'achat confirmé par ce membre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('acheteur_hors_liste')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('acheteur_hors_liste');
        });
    }
};
