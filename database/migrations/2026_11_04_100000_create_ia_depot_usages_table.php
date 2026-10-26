<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Une ligne par appel à l'IA dépôt : limite par membre et suivi du coût. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_depot_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('modele', 60);
            $table->unsignedSmallInteger('photos')->default(0);
            $table->unsignedInteger('jetons_entree')->default(0);
            $table->unsignedInteger('jetons_sortie')->default(0);
            $table->decimal('cout_usd', 10, 6)->default(0);
            $table->string('statut', 20)->default('ok'); // ok | erreur | refus
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_depot_usages');
    }
};
