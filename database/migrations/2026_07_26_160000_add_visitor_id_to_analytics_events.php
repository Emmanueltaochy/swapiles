<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point 14 : le comptage « visiteurs uniques » se basait sur session_id, qui
 * change trop souvent (régénéré à la connexion, nouveau par invité) -> ×4-5 de
 * surcompte vs GA. On ajoute un identifiant visiteur STABLE (cookie longue durée
 * swp_vid) pour un comptage aligné « 1 personne = 1 identifiant sur la période ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('analytics_events')) {
            return;
        }

        Schema::table('analytics_events', function (Blueprint $table) {
            if (!Schema::hasColumn('analytics_events', 'visitor_id')) {
                $table->string('visitor_id', 64)->nullable()->index()->after('session_id');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('analytics_events') && Schema::hasColumn('analytics_events', 'visitor_id')) {
            Schema::table('analytics_events', function (Blueprint $table) {
                $table->dropColumn('visitor_id');
            });
        }
    }
};
