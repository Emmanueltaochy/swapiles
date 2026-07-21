<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->string('sharetribe_id')->nullable()->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('price')->default(0);
            $table->string('currency')->default('EUR');
            $table->enum('listing_type', ['achat','echange-produits','don','location-vetements','negoce-prix'])->default('achat');
            $table->enum('status', ['draft','published','closed','sold'])->default('draft');
            $table->string('territoire')->default('la-reunion');
            $table->string('category_level1')->nullable();
            $table->string('category_level2')->nullable();
            $table->string('category_level3')->nullable();
            $table->string('etat')->nullable();
            $table->string('marque')->nullable();
            $table->string('taille')->nullable();
            $table->json('couleurs')->nullable();
            $table->string('location_address')->nullable();
            $table->boolean('pickup_enabled')->default(true);
            $table->boolean('shipping_enabled')->default(false);
            $table->integer('shipping_price')->default(0);
            $table->integer('views_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('listings'); }
};
