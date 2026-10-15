<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replay interactif : on rejoue le chat, les cœurs et l'article mis en avant
 * au bon moment de la vidéo. Il faut donc savoir quand l'enregistrement a
 * commencé, à quel article se rapporte un message, et quand partent les cœurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->timestamp('enregistrement_debut_at')->nullable();
        });

        Schema::table('live_messages', function (Blueprint $table) {
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();
        });

        // Cœurs regroupés par seconde (un compteur, pas une ligne par cœur).
        Schema::create('live_coeurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->timestamp('instant');
            $table->unsignedInteger('n')->default(1);
            $table->unique(['live_session_id', 'instant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_coeurs');
        Schema::table('live_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('listing_id');
        });
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn('enregistrement_debut_at');
        });
    }
};
