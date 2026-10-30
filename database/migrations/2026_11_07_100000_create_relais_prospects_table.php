<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('relais_prospects')) {
            return;
        }

        Schema::create('relais_prospects', function (Blueprint $table) {
            $table->id();
            // Identifiant dans la source (ex. « osm:node/123 ») : pas de doublon à chaque recherche.
            $table->string('source_id', 64)->unique();
            $table->string('nom', 160);
            $table->string('type', 40)->nullable();
            $table->string('adresse', 255)->nullable();
            $table->string('code_postal', 10)->nullable();
            $table->string('ville', 120)->nullable()->index();
            $table->string('territoire', 40)->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('telephone', 40)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('site_web', 255)->nullable();
            $table->string('horaires', 255)->nullable();
            $table->timestamp('email_cherche_at')->nullable();
            $table->string('statut', 20)->default('nouveau')->index();
            $table->timestamp('contacte_at')->nullable();
            $table->unsignedSmallInteger('nb_envois')->default(0);
            $table->string('jeton', 40)->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relais_prospects');
    }
};
