<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('relais_prospects', 'clique_at')) {
            return;
        }

        Schema::table('relais_prospects', function (Blueprint $table) {
            // Clic sur le bouton du mail de présentation (premier clic, et combien de fois).
            $table->timestamp('clique_at')->nullable();
            $table->unsignedSmallInteger('nb_clics')->default(0);
            // Formulaire « Devenir point relais » rempli après le mail.
            $table->timestamp('formulaire_at')->nullable();
            // Parcours après le clic : pages vues et boutons touchés (40 derniers).
            $table->json('parcours')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('relais_prospects', function (Blueprint $table) {
            $table->dropColumn(['clique_at', 'nb_clics', 'formulaire_at', 'parcours']);
        });
    }
};
