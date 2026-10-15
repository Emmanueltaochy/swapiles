<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des notifications push : chaque envoi (automatique ou lancé depuis
 * l'admin), son sort (parti, retenu la nuit, plafond…) et qui a cliqué.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_envois', function (Blueprint $table) {
            $table->id();
            // Destinataire ; null = envoi à tous les appareils (diffusion).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->string('type', 60)->nullable()->index();
            // auto | manuel
            $table->string('origine', 12)->default('auto')->index();
            $table->string('titre', 255);
            $table->text('message')->nullable();
            $table->string('url', 500)->nullable();
            // en_file | differe | envoye | echec | sans_appareil | ignore | coupe
            $table->string('statut', 16)->index();
            $table->string('raison', 255)->nullable();
            $table->timestamp('prevu_at')->nullable();
            $table->timestamp('envoye_at')->nullable();
            $table->unsignedInteger('appareils')->default(0);
            $table->unsignedInteger('appareils_ok')->default(0);
            $table->unsignedInteger('clics')->default(0);
            $table->timestamp('premier_clic_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('push_clics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_envoi_id')->constrained('push_envois')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Un clic par personne (membre ou visiteur) et par envoi.
            $table->string('cle', 64);
            $table->string('plateforme', 12)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->unique(['push_envoi_id', 'cle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_clics');
        Schema::dropIfExists('push_envois');
    }
};
