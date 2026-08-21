<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points relais acceptés :
 *  - relay_point_user   : réglage par défaut du vendeur (ses relais habituels).
 *  - listing_relay_point : surcharge par annonce (prioritaire si renseignée).
 * L'acheteur choisit ensuite, parmi ce périmètre, le relais le plus proche.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('relay_point_user')) {
            Schema::create('relay_point_user', function (Blueprint $table) {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('relay_point_id')->constrained()->cascadeOnDelete();
                $table->primary(['user_id', 'relay_point_id']);
            });
        }

        if (! Schema::hasTable('listing_relay_point')) {
            Schema::create('listing_relay_point', function (Blueprint $table) {
                $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
                $table->foreignId('relay_point_id')->constrained()->cascadeOnDelete();
                $table->primary(['listing_id', 'relay_point_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_relay_point');
        Schema::dropIfExists('relay_point_user');
    }
};
