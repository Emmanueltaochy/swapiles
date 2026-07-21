<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('listing_offers')) { Schema::create('listing_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->integer('amount');
            $table->string('status')->default('pending');
            $table->text('message')->nullable();
            $table->timestamps();
        }); }
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_offers');
    }
};
