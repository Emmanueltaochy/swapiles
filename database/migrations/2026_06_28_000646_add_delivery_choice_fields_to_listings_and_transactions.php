<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (!Schema::hasColumn('listings', 'hand_delivery_location')) {
                $table->string('hand_delivery_location')->nullable()->after('location_address');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            foreach ([
                'buyer_full_name' => 'delivery_method',
                'buyer_phone' => 'buyer_full_name',
                'shipping_address_line1' => 'buyer_phone',
                'shipping_address_line2' => 'shipping_address_line1',
                'shipping_postal_code' => 'shipping_address_line2',
                'shipping_city' => 'shipping_postal_code',
                'shipping_country' => 'shipping_city',
                'hand_delivery_location' => 'shipping_country',
            ] as $column => $after) {
                if (!Schema::hasColumn('transactions', $column)) {
                    $table->string($column)->nullable()->after($after);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (Schema::hasColumn('listings', 'hand_delivery_location')) {
                $table->dropColumn('hand_delivery_location');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            foreach ([
                'buyer_full_name',
                'buyer_phone',
                'shipping_address_line1',
                'shipping_address_line2',
                'shipping_postal_code',
                'shipping_city',
                'shipping_country',
                'hand_delivery_location',
            ] as $column) {
                if (Schema::hasColumn('transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
