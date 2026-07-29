<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traçabilité des comptes : à l'inscription et à chaque connexion, on capte
 * l'IP client réelle, le user-agent et une empreinte d'appareil (hash calculé
 * côté client). Permet de prouver que plusieurs comptes sont la même personne
 * (même IP / même empreinte) et d'appuyer la modération anti-multicompte.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_sessions')) {
            return;
        }

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip', 45)->nullable()->index();          // IPv4 ou IPv6
            $table->text('user_agent')->nullable();
            $table->string('device_fingerprint', 128)->nullable()->index();
            $table->string('event_type', 20)->index();               // registration | login
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
