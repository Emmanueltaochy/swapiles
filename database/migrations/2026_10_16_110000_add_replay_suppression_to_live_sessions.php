<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replay : gardé à vie, sauf si le vendeur le refuse à la fin du live ou le
 * supprime plus tard. La vidéo est alors effacée du stockage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->timestamp('replay_supprime_at')->nullable();
            $table->timestamp('replay_fichier_efface_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn(['replay_supprime_at', 'replay_fichier_efface_at']);
        });
    }
};
