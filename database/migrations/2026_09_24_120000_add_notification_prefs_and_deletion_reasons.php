<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1. Réglages de notification par membre.
 *    Un membre ne pouvait rien couper : ni push, ni e-mail d'animation. Le seul
 *    moyen d'arrêter d'être sollicité était de supprimer son compte.
 *
 * 2. Motif de suppression de compte.
 *    On ne savait pas POURQUOI les membres partaient. Cette table le consigne,
 *    sans aucune donnée personnelle : seuls le motif, la date et deux repères
 *    d'ancienneté sont conservés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_prefs')->nullable();
        });

        Schema::create('account_deletion_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('reason', 40);            // clé du motif choisi
            $table->text('details')->nullable();     // précision libre, facultative
            $table->unsignedInteger('days_since_signup')->nullable();
            $table->boolean('had_sales')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_reasons');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_prefs');
        });
    }
};
