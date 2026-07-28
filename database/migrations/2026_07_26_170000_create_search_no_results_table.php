<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point 17 : journal des recherches SANS résultat, pour révéler la demande hors
 * catalogue (PS5, iPhone, canapé…) et prioriser l'offre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_no_results')) {
            return;
        }

        Schema::create('search_no_results', function (Blueprint $table) {
            $table->id();
            $table->string('term', 191)->index();          // requête normalisée (minuscule)
            $table->string('raw_term', 255)->nullable();     // saisie brute
            $table->foreignId('user_id')->nullable()->index();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_no_results');
    }
};
