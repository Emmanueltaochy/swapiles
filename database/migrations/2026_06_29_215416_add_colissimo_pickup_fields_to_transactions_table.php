<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'colissimo_delivery_type')) {
                $table->string('colissimo_delivery_type')->nullable()->after('delivery_method');
            }
            if (!Schema::hasColumn('transactions', 'pickup_id')) {
                $table->string('pickup_id')->nullable()->after('colissimo_delivery_type');
            }
            if (!Schema::hasColumn('transactions', 'pickup_name')) {
                $table->string('pickup_name')->nullable()->after('pickup_id');
            }
            if (!Schema::hasColumn('transactions', 'pickup_address')) {
                $table->string('pickup_address')->nullable()->after('pickup_name');
            }
            if (!Schema::hasColumn('transactions', 'pickup_postal_code')) {
                $table->string('pickup_postal_code', 20)->nullable()->after('pickup_address');
            }
            if (!Schema::hasColumn('transactions', 'pickup_city')) {
                $table->string('pickup_city', 120)->nullable()->after('pickup_postal_code');
            }
            if (!Schema::hasColumn('transactions', 'pickup_country')) {
                $table->string('pickup_country', 10)->nullable()->after('pickup_city');
            }
            if (!Schema::hasColumn('transactions', 'pickup_type')) {
                $table->string('pickup_type', 30)->nullable()->after('pickup_country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            foreach ([
                'colissimo_delivery_type',
                'pickup_id',
                'pickup_name',
                'pickup_address',
                'pickup_postal_code',
                'pickup_city',
                'pickup_country',
                'pickup_type',
            ] as $column) {
                if (Schema::hasColumn('transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
