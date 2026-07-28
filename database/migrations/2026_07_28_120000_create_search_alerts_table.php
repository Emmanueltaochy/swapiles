<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point 17b : alertes « préviens-moi ». Sur une recherche SANS résultat,
 * l'internaute laisse son e-mail pour être prévenu quand une annonce
 * correspondant au terme est publiée. On CAPTURE seulement — aucun e-mail
 * n'est envoyé tant que la délivrabilité (DKIM) n'est pas réglée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_alerts')) {
            return;
        }

        Schema::create('search_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('term', 191)->index();        // requête normalisée (minuscule)
            $table->string('raw_term', 255)->nullable();  // saisie brute affichée
            $table->string('email', 191)->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('visitor_id', 64)->nullable()->index();
            $table->string('territoire', 100)->nullable();
            $table->timestamp('notified_at')->nullable();  // rempli quand l'alerte est envoyée (futur)
            $table->timestamps();

            // Un même e-mail ne s'abonne qu'une fois par terme.
            $table->unique(['term', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_alerts');
    }
};
