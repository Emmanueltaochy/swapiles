<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remontées d'annonces (voir App\Support\Remontees) : une annonce plus ancienne
 * peut repasser en tête de liste (baisse de prix, rotation des anciennes).
 * Le classement utilise remonte_at quand il existe, sinon la date de publication.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('remonte_at')->nullable()->index();
            $table->timestamp('prix_remonte_at')->nullable();
            $table->timestamp('conseil_prix_at')->nullable();
        });

        Schema::create('listing_remontees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            // prix = baisse de prix ; rotation = ancienne annonce remise en avant.
            $table->string('motif', 20);
            $table->unsignedInteger('vues_avant')->default(0);
            $table->timestamps();
            $table->index(['motif', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_remontees');

        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['remonte_at']);
            $table->dropColumn(['remonte_at', 'prix_remonte_at', 'conseil_prix_at']);
        });
    }
};
