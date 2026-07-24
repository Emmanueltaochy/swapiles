<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            // Date d'envoi du rappel « N'oubliez pas votre favori » (une seule fois).
            $table->timestamp('reminded_at')->nullable()->after('listing_id');
        });
    }

    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};
