<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'carrier')) {
                $table->string('carrier')->nullable()->after('shipping_status');
            }

            if (!Schema::hasColumn('transactions', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->after('carrier');
            }

            if (!Schema::hasColumn('transactions', 'tracking_url')) {
                $table->string('tracking_url')->nullable()->after('tracking_number');
            }

            if (!Schema::hasColumn('transactions', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('received_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'carrier',
                'tracking_number',
                'tracking_url',
                'delivered_at',
            ]);
        });
    }
};
