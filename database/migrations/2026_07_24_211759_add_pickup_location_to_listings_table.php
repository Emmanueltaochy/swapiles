<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Ville + code postal de la remise en main propre : servent à situer
            // l'annonce sur la carte (au niveau de la commune uniquement).
            $table->string('pickup_city')->nullable()->after('hand_delivery_location');
            $table->string('pickup_postal_code', 20)->nullable()->after('pickup_city');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['pickup_city', 'pickup_postal_code']);
        });
    }
};
