<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Réponse envoyée au prospect depuis contact@ (par le CEO, après confirmation d'Emmanuel). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_reponses', function (Blueprint $t) {
            $t->timestamp('envoye_at')->nullable();
            $t->text('texte_envoye')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agent_reponses', fn (Blueprint $t) => $t->dropColumn(['envoye_at', 'texte_envoye']));
    }
};
