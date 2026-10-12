<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ventes en main propre déclarées par le vendeur : l'acheteur désigné peut
 * confirmer (« oui, c'est bien moi ») ou contester. Sans ça, impossible de
 * distinguer une vraie vente d'une annonce simplement retirée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('acheteur_confirme_at')->nullable();
            $table->timestamp('acheteur_conteste_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['acheteur_confirme_at', 'acheteur_conteste_at']);
        });
    }
};
