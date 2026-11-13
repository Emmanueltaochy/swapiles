<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sondage « Aimeriez-vous faire du live shopping ? » et demande d'accès aux lives. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('live_interet', 20)->nullable();
            $t->timestamp('live_interet_at')->nullable();
            $t->string('live_demande_statut', 20)->nullable()->index();
            $t->timestamp('live_demande_at')->nullable();
            $t->text('live_demande_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['live_interet', 'live_interet_at', 'live_demande_statut', 'live_demande_at', 'live_demande_message']));
    }
};
