<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les inscrits d'aujourd'hui se sont enregistrés avant l'ajout du choix du
 * territoire (campagne pub La Réunion). On les rattache donc à « La Réunion ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'territoire')) {
            return;
        }

        DB::table('users')
            ->whereDate('created_at', now()->toDateString())
            ->update(['territoire' => 'La Réunion']);
    }

    public function down(): void
    {
        // Pas de retour arrière : correction de données ponctuelle.
    }
};
