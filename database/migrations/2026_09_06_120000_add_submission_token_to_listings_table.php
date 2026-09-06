<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jeton d'envoi du formulaire de dépôt d'annonce.
 *
 * Chaque affichage du formulaire porte un jeton unique. Si le même formulaire
 * est envoyé deux fois (double tap sur mobile, actualisation pendant l'envoi
 * des photos, retour arrière), la contrainte d'unicité empêche la création
 * d'une seconde annonce identique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('submission_token', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropUnique(['submission_token']);
            $table->dropColumn('submission_token');
        });
    }
};
