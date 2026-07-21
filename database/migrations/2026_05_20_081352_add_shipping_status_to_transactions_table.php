<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {

            if (!Schema::hasColumn('transactions', 'shipping_status')) {
                $table->string('shipping_status')
                    ->default('paid')
                    ->after('status');
            }

            if (!Schema::hasColumn('transactions', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable();
            }

            if (!Schema::hasColumn('transactions', 'received_at')) {
                $table->timestamp('received_at')->nullable();
            }

            if (!Schema::hasColumn('transactions', 'released_at')) {
                $table->timestamp('released_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_status',
                'shipped_at',
                'received_at',
                'released_at',
            ]);
        });
    }
};
