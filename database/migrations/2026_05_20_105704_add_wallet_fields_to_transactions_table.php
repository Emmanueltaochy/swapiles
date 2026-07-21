<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {

            if (!Schema::hasColumn('transactions', 'wallet_status')) {
                $table->string('wallet_status')->default('pending_confirmation');
            }

            if (!Schema::hasColumn('transactions', 'transfer_started_at')) {
                $table->timestamp('transfer_started_at')->nullable();
            }

            if (!Schema::hasColumn('transactions', 'transferred_at')) {
                $table->timestamp('transferred_at')->nullable();
            }

            if (!Schema::hasColumn('transactions', 'estimated_payout_date')) {
                $table->timestamp('estimated_payout_date')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'wallet_status',
                'transfer_started_at',
                'transferred_at',
                'estimated_payout_date',
            ]);
        });
    }
};
