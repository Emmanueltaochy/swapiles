<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item 9 : on réutilise un même Customer Stripe par acheteur (au lieu d'en
 * recréer un à chaque PaymentIntent). On mémorise donc son id côté user.
 * Additif et réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'stripe_customer_id')) {
                $table->string('stripe_customer_id')->nullable()->after('stripe_account_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'stripe_customer_id')) {
                $table->dropColumn('stripe_customer_id');
            }
        });
    }
};
