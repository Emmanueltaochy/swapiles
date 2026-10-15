<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vues par annonce et par jour : la « popularité » de l'accueil se calcule
 * sur les derniers jours, pas sur le compteur depuis toujours (qui figeait
 * toujours les mêmes annonces en tête).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_vues_jours', function (Blueprint $table) {
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->date('jour');
            $table->unsignedInteger('vues')->default(0);
            $table->primary(['listing_id', 'jour']);
            $table->index('jour');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_vues_jours');
    }
};
