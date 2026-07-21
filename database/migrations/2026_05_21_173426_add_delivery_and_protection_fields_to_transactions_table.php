<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'delivery_method')) {
                $table->string('delivery_method')->default('secure_hand_delivery')->after('payment_method');
            }

            if (!Schema::hasColumn('transactions', 'buyer_protection_fee')) {
                $table->integer('buyer_protection_fee')->default(0)->after('commission');
            }

            if (!Schema::hasColumn('transactions', 'shipping_fee')) {
                $table->integer('shipping_fee')->default(0)->after('buyer_protection_fee');
            }

            if (!Schema::hasColumn('transactions', 'seller_amount')) {
                $table->integer('seller_amount')->default(0)->after('shipping_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_method',
                'buyer_protection_fee',
                'shipping_fee',
                'seller_amount',
            ]);
        });
    }
};
