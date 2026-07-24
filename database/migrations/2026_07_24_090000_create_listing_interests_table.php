<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes d'intérêt inter-îles : un acheteur d'une autre île signale qu'il
 * veut un produit dont le vendeur n'a pas encore activé Colissimo. Quand le
 * vendeur active Colissimo, ces acheteurs sont prévenus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('listing_interests')) {
            return;
        }

        Schema::create('listing_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('buyer_territoire')->nullable();
            $table->timestamp('notified_buyer_at')->nullable();
            $table->timestamps();

            $table->unique(['listing_id', 'buyer_id']);
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_interests');
    }
};
