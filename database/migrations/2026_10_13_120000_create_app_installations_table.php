<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une ligne par installation de l'appli qui a été OUVERTE au moins une fois.
 * Identifiant tiré au hasard par l'appli au premier lancement (aucun
 * identifiant de l'appareil, aucun lien avec le compte) : il disparaît avec
 * la désinstallation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_installations', function (Blueprint $table) {
            $table->id();
            $table->string('install_id', 64)->unique();
            $table->string('platform', 10)->index(); // ios | android
            $table->unsignedInteger('ouvertures')->default(1);
            $table->timestamp('premiere_ouverture_at')->index();
            $table->timestamp('derniere_ouverture_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_installations');
    }
};
