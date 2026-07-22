<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exchange_proposals')) {
            return;
        }

        Schema::create('exchange_proposals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->unsignedBigInteger('proposer_id');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('offered_listing_id')->nullable();
            $table->string('offered_title')->nullable();
            $table->string('offered_condition')->nullable();
            $table->text('offered_description')->nullable();
            $table->string('offered_photo_path')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index('listing_id');
            $table->index('proposer_id');
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_proposals');
    }
};
