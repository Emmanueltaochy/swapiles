<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('analytics_events')) {
            return;
        }

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('session_id')->nullable()->index();
            $table->string('ip_address', 80)->nullable();

            $table->string('method', 10)->nullable();
            $table->string('path', 500)->nullable()->index();
            $table->text('url')->nullable();
            $table->text('referer')->nullable();

            $table->string('route_name', 255)->nullable()->index();
            $table->string('page_name', 255)->nullable()->index();

            $table->string('device', 50)->nullable();
            $table->string('browser', 80)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
