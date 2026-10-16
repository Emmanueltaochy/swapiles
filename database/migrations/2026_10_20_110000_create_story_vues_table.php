<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stories automatiques : jusqu'où chaque membre a regardé les nouveautés
 * d'un dressing (le rond redevient gris une fois vu, vert au prochain article).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_vues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendeur_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('vu_jusqu_a');
            $table->unique(['user_id', 'vendeur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_vues');
    }
};
