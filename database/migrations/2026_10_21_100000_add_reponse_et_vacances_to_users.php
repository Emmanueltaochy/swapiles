<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - reponse_minutes : délai de réponse habituel (médiane, 60 derniers jours),
 *   recalculé chaque nuit → badge « Répond vite ».
 * - vacances_* : mode vacances (dressing en pause jusqu'à une date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('reponse_minutes')->nullable();
            $table->unsignedSmallInteger('reponse_mesures')->default(0);
            $table->date('vacances_jusqu_au')->nullable();
            $table->string('vacances_message', 140)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['reponse_minutes', 'reponse_mesures', 'vacances_jusqu_au', 'vacances_message']);
        });
    }
};
