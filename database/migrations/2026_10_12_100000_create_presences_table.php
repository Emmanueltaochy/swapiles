<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Présences confirmées par le navigateur (visiteurs humains en direct).
 *
 * live_visits compte chaque page demandée au serveur : robots déguisés,
 * vérificateurs de publicités et aperçus de liens y passent aussi. Ici, une
 * ligne n'existe que si la page a réellement été ouverte dans un navigateur
 * et que quelqu'un l'a touchée (défilement, clic, appui).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            // Empreinte de la session (jamais la session elle-même).
            $table->string('cle', 64)->unique();
            // Même empreinte d'IP que live_visits : pour isoler le trafic non confirmé.
            $table->string('ip_hash', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('territoire')->nullable();
            $table->string('path', 500)->nullable();
            $table->string('appareil', 20)->default('mobile');
            $table->boolean('appli')->default(false);
            $table->string('source', 20)->default('direct');
            $table->unsignedInteger('pages')->default(1);
            $table->timestamp('debut_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences');
    }
};
