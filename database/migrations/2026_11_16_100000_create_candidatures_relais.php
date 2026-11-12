<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Commerçants qui demandent à devenir point relais (formulaire « Devenir point relais »). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidatures_relais', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('commerce', 160);
            $t->string('contact', 120)->nullable();
            $t->string('ville', 120)->nullable();
            $t->string('territoire', 40)->nullable()->index();
            $t->string('telephone', 40)->nullable();
            $t->string('email', 191)->nullable()->index();
            $t->string('horaires', 191)->nullable();
            $t->text('message')->nullable();
            $t->string('source', 20)->default('formulaire');
            $t->string('message_id', 255)->nullable()->unique();
            $t->string('statut', 20)->default('nouvelle')->index();
            $t->foreignId('relay_point_id')->nullable()->constrained('relay_points')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidatures_relais');
    }
};
