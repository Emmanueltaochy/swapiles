<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('sharetribe_id')->nullable()->unique();
            $table->foreignId('listing_id')->constrained()->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
            $table->integer('amount')->default(0);
            $table->integer('commission')->default(0);
            $table->string('currency')->default('EUR');
            $table->enum('payment_method', ['cb','especes','echange','don'])->default('especes');
            $table->enum('status', ['inquiry','pending','paid','completed','cancelled','refunded'])->default('inquiry');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('transactions'); }
};
