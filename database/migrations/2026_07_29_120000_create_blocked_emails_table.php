<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liste noire d'e-mails, indépendante de la table users : un e-mail bloqué le
 * reste même si le compte est supprimé (impossible de se réinscrire avec).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blocked_emails')) {
            return;
        }

        Schema::create('blocked_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->unique();      // normalisé (minuscule)
            $table->string('reason', 255)->nullable();
            $table->foreignId('blocked_by')->nullable();  // admin ayant bloqué
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_emails');
    }
};
