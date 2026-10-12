<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une ligne par visiteur HUMAIN et par jour (confirmé par son navigateur,
 * voir VisiteursEnDirect). Remplace, pour « visiteurs uniques », le comptage
 * des pages vues côté serveur, gonflé par les robots sans cookie (chaque
 * page qu'ils demandent y devenait un nouveau visiteur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visiteurs_jours', function (Blueprint $table) {
            $table->id();
            $table->date('jour')->index();
            $table->string('cle', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('appli')->default(false);
            $table->string('source', 20)->default('direct');
            $table->string('territoire')->nullable();
            $table->unique(['jour', 'cle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visiteurs_jours');
    }
};
