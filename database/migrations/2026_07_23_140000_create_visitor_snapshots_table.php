<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique du nombre de visiteurs connectés simultanément (relevé toutes les
 * ~5 min par une tâche planifiée). Permet de tracer la courbe de fréquentation
 * dans la journée et de repérer les pics (« 15 connectés en même temps à 20h »).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visitor_snapshots')) {
            return;
        }

        Schema::create('visitor_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('live_count')->default(0);
            $table->unsignedInteger('members_count')->default(0);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_snapshots');
    }
};
