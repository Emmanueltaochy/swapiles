<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table de mapping entre les UUIDs Sharetribe et les IDs locaux Laravel.
 *
 * Sert à rendre l'import idempotent : on peut relancer la commande
 * sans dupliquer les données. Sert aussi à résoudre les références
 * croisées (un listing pointe vers un user via son UUID Sharetribe).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sharetribe_imports', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50); // 'user', 'listing', 'transaction', 'message', 'image'
            $table->string('external_id', 100); // UUID Sharetribe
            $table->unsignedBigInteger('local_id'); // ID dans la table Laravel correspondante
            $table->json('payload')->nullable(); // Backup brut pour debug
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            $table->unique(['entity_type', 'external_id']);
            $table->index(['entity_type', 'local_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sharetribe_imports');
    }
};
