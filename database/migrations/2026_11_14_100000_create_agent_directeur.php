<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent directeur : réglages durables, prospection RSE (collectivités),
 * réponses reçues, résumés quotidiens ; relance et réponse pour les relais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglages', function (Blueprint $t) {
            $t->string('cle', 120)->primary();
            $t->text('valeur')->nullable();
            $t->timestamps();
        });

        Schema::create('prospects_rse', function (Blueprint $t) {
            $t->id();
            $t->string('source_id', 120)->unique();
            $t->string('nom', 200);
            $t->string('type', 40)->index();
            $t->string('territoire', 40)->index();
            $t->string('ville', 120)->nullable();
            $t->string('code_postal', 10)->nullable();
            $t->string('email', 190)->nullable()->index();
            $t->string('site_web', 255)->nullable();
            $t->string('telephone', 40)->nullable();
            $t->string('statut', 20)->default('nouveau')->index();
            $t->timestamp('contacte_at')->nullable();
            $t->timestamp('relance_at')->nullable();
            $t->timestamp('repondu_at')->nullable();
            $t->unsignedTinyInteger('nb_envois')->default(0);
            $t->string('jeton', 40)->unique();
            $t->timestamp('clique_at')->nullable();
            $t->unsignedInteger('nb_clics')->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::table('relais_prospects', function (Blueprint $t) {
            $t->timestamp('repondu_at')->nullable();
        });

        Schema::create('agent_rapports', function (Blueprint $t) {
            $t->id();
            $t->date('jour')->unique();
            $t->json('chiffres')->nullable();
            $t->longText('texte')->nullable();
            $t->json('actions')->nullable();
            $t->decimal('cout_usd', 10, 6)->default(0);
            $t->timestamps();
        });

        Schema::create('agent_reponses', function (Blueprint $t) {
            $t->id();
            $t->string('message_id', 255)->unique();
            $t->string('prospect_type', 20);
            $t->unsignedBigInteger('prospect_id');
            $t->string('expediteur', 190);
            $t->string('sujet', 255)->nullable();
            $t->timestamp('recu_at')->nullable();
            $t->string('categorie', 30)->index();
            $t->text('resume')->nullable();
            $t->text('brouillon')->nullable();
            $t->text('extrait')->nullable();
            $t->timestamp('vu_at')->nullable();
            $t->timestamps();
            $t->index(['prospect_type', 'prospect_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_reponses');
        Schema::dropIfExists('agent_rapports');
        Schema::table('relais_prospects', fn (Blueprint $t) => $t->dropColumn('repondu_at'));
        Schema::dropIfExists('prospects_rse');
        Schema::dropIfExists('reglages');
    }
};
