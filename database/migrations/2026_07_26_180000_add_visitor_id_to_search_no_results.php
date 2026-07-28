<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On stocke l'identifiant visiteur stable (cookie swp_vid) sur les recherches
 * sans résultat, pour compter les VISITEURS distincts (même anonymes) et
 * distinguer « 1 personne qui cherche 3 fois » d'un éventuel bot.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('search_no_results')) {
            return;
        }

        Schema::table('search_no_results', function (Blueprint $table) {
            if (!Schema::hasColumn('search_no_results', 'visitor_id')) {
                $table->string('visitor_id', 64)->nullable()->index()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('search_no_results') && Schema::hasColumn('search_no_results', 'visitor_id')) {
            Schema::table('search_no_results', function (Blueprint $table) {
                $table->dropColumn('visitor_id');
            });
        }
    }
};
