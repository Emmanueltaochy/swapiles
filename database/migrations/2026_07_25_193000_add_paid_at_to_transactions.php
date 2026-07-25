<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `paid_at` était écrit par le tunnel (CheckoutController::success) mais n'avait
 * aucune migration : il existe en prod (ajouté hors migration) mais pas en
 * dev/tests. On régularise la dérive de schéma. Gardé par hasColumn -> no-op en
 * prod, ajoute la colonne partout ailleurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        // On ne supprime pas : la colonne préexistait en prod hors migration.
    }
};
