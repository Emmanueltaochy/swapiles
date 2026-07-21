<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('colissimo_delivery_type')->nullable()->after('delivery_method'); // home / pickup
            $table->string('pickup_id')->nullable()->after('colissimo_delivery_type');
            $table->string('pickup_name')->nullable()->after('pickup_id');
            $table->string('pickup_address')->nullable()->after('pickup_name');
            $table->string('pickup_postal_code', 20)->nullable()->after('pickup_address');
            $table->string('pickup_city', 120)->nullable()->after('pickup_postal_code');
            $table->string('pickup_country', 10)->nullable()->after('pickup_city');
            $table->string('pickup_type', 30)->nullable()->after('pickup_country');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'colissimo_delivery_type',
                'pickup_id',
                'pickup_name',
                'pickup_address',
                'pickup_postal_code',
                'pickup_city',
                'pickup_country',
                'pickup_type',
            ]);
        });
    }
};
