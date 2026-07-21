<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Horodatage du signalement "vendeur n'a pas expédié -> à rembourser".
            // Évite de re-notifier l'admin à chaque passage du planificateur.
            $table->timestamp('auto_review_flagged_at')->nullable()->after('released_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('auto_review_flagged_at');
        });
    }
};
