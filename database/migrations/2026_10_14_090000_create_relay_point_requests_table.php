<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de membres : « je voudrais un point relais plus près de chez moi »,
 * avec, si possible, un commerce à démarcher.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relay_point_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('territoire', 40);
            $table->string('city', 120);
            $table->string('business_name', 160)->nullable();
            $table->string('business_address', 255)->nullable();
            // oui | non | ne_sait_pas
            $table->string('already_relay', 12)->default('ne_sait_pas');
            $table->json('networks')->nullable();
            $table->string('name', 120);
            $table->string('email', 191);
            $table->string('phone', 40)->nullable();
            $table->text('message')->nullable();
            // nouvelle | contactee | ouverte | sans_suite
            $table->string('status', 12)->default('nouvelle')->index();
            $table->timestamps();
            $table->index(['territoire', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relay_point_requests');
    }
};
