<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dernière demande de note de l'appli (pour ne pas redemander trop souvent). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'note_demandee_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('note_demandee_at')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('note_demandee_at'));
    }
};
