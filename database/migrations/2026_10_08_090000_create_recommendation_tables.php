<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Recommandé pour vous ».
 *
 *  - listing_consultations : les articles qu'un membre CONNECTÉ a vraiment
 *    ouverts (pas les préchargements). Avec ses favoris, c'est ce qui dit
 *    ce qu'il aime. Une ligne par membre et par article.
 *  - recommandations : les articles déjà proposés à un membre. On ne
 *    propose jamais deux fois le même, et la page « Pour vous » les affiche.
 *  - users.recommandations_envoyees_at : date du dernier envoi, pour n'en
 *    envoyer que de temps en temps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('vues')->default(1);
            $table->timestamp('derniere_vue_at')->nullable();

            $table->unique(['user_id', 'listing_id']);
            $table->index(['user_id', 'derniere_vue_at']);
        });

        Schema::create('recommandations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 6, 2)->default(0);
            $table->timestamp('envoye_at')->nullable();

            $table->unique(['user_id', 'listing_id']);
            $table->index(['user_id', 'envoye_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('recommandations_envoyees_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('recommandations_envoyees_at');
        });
        Schema::dropIfExists('recommandations');
        Schema::dropIfExists('listing_consultations');
    }
};
