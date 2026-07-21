<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (!Schema::hasColumn('listings', 'allows_offers')) {
                $table->boolean('allows_offers')->default(false)->after('listing_type');
            }
            if (!Schema::hasColumn('listings', 'allows_exchange')) {
                $table->boolean('allows_exchange')->default(false)->after('allows_offers');
            }
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (Schema::hasColumn('listings', 'allows_offers')) {
                $table->dropColumn('allows_offers');
            }
            if (Schema::hasColumn('listings', 'allows_exchange')) {
                $table->dropColumn('allows_exchange');
            }
        });
    }
};
