<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jetons d'appareil pour les notifications push (Firebase Cloud Messaging).
 * Chaque installation de l'app (Android/iOS) enregistre son jeton ; on peut
 * ainsi envoyer une notification à tous les porteurs de l'app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 20)->nullable(); // android | ios | web
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
