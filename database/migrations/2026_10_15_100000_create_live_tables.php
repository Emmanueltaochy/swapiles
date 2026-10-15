<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vente en direct (live shopping) : un vendeur présente ses articles en
 * vidéo, les spectateurs discutent et achètent pendant le live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('titre', 120);
            $table->text('description')->nullable();
            // programme | en_direct | termine
            $table->string('statut', 12)->default('programme')->index();
            $table->timestamp('debut_prevu_at')->nullable()->index();
            $table->timestamp('demarre_at')->nullable();
            $table->timestamp('termine_at')->nullable();
            $table->foreignId('article_epingle_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->unsignedInteger('spectateurs_max')->default(0);
            $table->unsignedInteger('coeurs')->default(0);
            // Coupé par la modération (raison visible dans l'admin).
            $table->string('coupe_raison', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('live_session_listing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ordre')->default(0);
            $table->unique(['live_session_id', 'listing_id']);
        });

        Schema::create('live_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('body', 300);
            // message | epingle | vente | systeme
            $table->string('type', 12)->default('message');
            $table->boolean('masque')->default(false);
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('live_spectateurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->string('cle', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('exclu')->default(false);
            $table->timestamp('last_seen_at')->index();
            $table->unique(['live_session_id', 'cle']);
        });

        // Article réservé quelques minutes pendant le paiement d'un acheteur du live.
        Schema::create('live_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('live_session_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expire_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_reservations');
        Schema::dropIfExists('live_spectateurs');
        Schema::dropIfExists('live_messages');
        Schema::dropIfExists('live_session_listing');
        Schema::dropIfExists('live_sessions');
    }
};
