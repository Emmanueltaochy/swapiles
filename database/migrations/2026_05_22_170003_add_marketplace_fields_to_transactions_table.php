<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {

            if (!Schema::hasColumn('transactions', 'buyer_protection_fee')) {
                $table->integer('buyer_protection_fee')->default(0);
            }

            if (!Schema::hasColumn('transactions', 'platform_commission')) {
                $table->integer('platform_commission')->default(0);
            }

            if (!Schema::hasColumn('transactions', 'seller_amount')) {
                $table->integer('seller_amount')->default(0);
            }

            if (!Schema::hasColumn('transactions', 'delivery_method')) {
                $table->string('delivery_method')->nullable();
            }

            if (!Schema::hasColumn('transactions', 'released_at')) {
                $table->timestamp('released_at')->nullable();
            }

            if (!Schema::hasColumn('transactions', 'shipping_status')) {
                $table->string('shipping_status')->default('pending');
            }

            if (!Schema::hasColumn('transactions', 'stripe_transfer_id')) {
                $table->string('stripe_transfer_id')->nullable();
            }

        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'buyer_protection_fee',
                'platform_commission',
                'seller_amount',
                'delivery_method',
                'released_at',
                'shipping_status',
                'stripe_transfer_id',
            ]);
        });
    }
};
