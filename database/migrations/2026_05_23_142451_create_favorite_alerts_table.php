<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('favorite_alerts')) {
            Schema::create('favorite_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type')->default('price_drop');
                $table->integer('old_price')->nullable();
                $table->integer('new_price')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_alerts');
    }
};
