<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache un compte « gérant » (le commerçant) à un point relais. Ce compte
 * accède à son espace relais : réception des colis, remise avec vérification du
 * code de retrait, et suivi de son solde (1 € par colis remis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relay_points', function (Blueprint $table) {
            if (! Schema::hasColumn('relay_points', 'manager_user_id')) {
                $table->foreignId('manager_user_id')->nullable()->after('name')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('relay_points', function (Blueprint $table) {
            if (Schema::hasColumn('relay_points', 'manager_user_id')) {
                $table->dropConstrainedForeignId('manager_user_id');
            }
        });
    }
};
