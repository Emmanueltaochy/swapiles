<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modération : signalement d'un message (paiement hors plateforme envoyé quand
 * même, ou échange de numéro sur les premiers messages). Additif et idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'flagged_at')) {
                $table->timestamp('flagged_at')->nullable()->index();
            }
            if (! Schema::hasColumn('messages', 'flag_kind')) {
                $table->string('flag_kind', 30)->nullable()->index(); // payment_forced | phone
            }
            if (! Schema::hasColumn('messages', 'flag_reason')) {
                $table->string('flag_reason', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            foreach (['flagged_at', 'flag_kind', 'flag_reason'] as $col) {
                if (Schema::hasColumn('messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
