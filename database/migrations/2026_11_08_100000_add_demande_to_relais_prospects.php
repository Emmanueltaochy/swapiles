<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('relais_prospects', 'demande')) {
            return;
        }

        Schema::table('relais_prospects', function (Blueprint $table) {
            // Demande dans la ville du commerce (annonces + demandes de relais) : sert au tri.
            $table->unsignedInteger('demande')->default(0)->index();
        });
    }

    public function down(): void
    {
        Schema::table('relais_prospects', function (Blueprint $table) {
            $table->dropIndex(['demande']);
            $table->dropColumn('demande');
        });
    }
};
