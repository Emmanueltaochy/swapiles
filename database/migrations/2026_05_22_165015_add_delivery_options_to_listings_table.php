<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (!Schema::hasColumn('listings', 'allows_hand_delivery')) {
                $table->boolean('allows_hand_delivery')->default(true)->after('shipping_enabled');
            }

            if (!Schema::hasColumn('listings', 'allows_colissimo')) {
                $table->boolean('allows_colissimo')->default(false)->after('allows_hand_delivery');
            }

            if (!Schema::hasColumn('listings', 'requires_online_payment')) {
                $table->boolean('requires_online_payment')->default(false)->after('allows_colissimo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn([
                'allows_hand_delivery',
                'allows_colissimo',
                'requires_online_payment',
            ]);
        });
    }
};
