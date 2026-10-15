<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vente en direct, 2e étape :
 *  - articles masqués au départ, dévoilés par le vendeur pendant le live ;
 *  - « Me prévenir » sur les lives à venir ;
 *  - enregistrement du live et replay publié sur le profil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_session_listing', function (Blueprint $table) {
            $table->boolean('visible')->default(false);
        });
        // Lives déjà créés : leurs articles restent visibles comme avant.
        DB::table('live_session_listing')->update(['visible' => true]);

        Schema::table('live_sessions', function (Blueprint $table) {
            $table->timestamp('rappel_envoye_at')->nullable();
            $table->string('enregistrement_id', 64)->nullable();
            $table->string('replay_chemin', 255)->nullable();
            $table->timestamp('replay_publie_at')->nullable()->index();
        });

        Schema::create('live_rappels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['live_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_rappels');
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn(['rappel_envoye_at', 'enregistrement_id', 'replay_chemin', 'replay_publie_at']);
        });
        Schema::table('live_session_listing', function (Blueprint $table) {
            $table->dropColumn('visible');
        });
    }
};
