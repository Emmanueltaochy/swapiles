<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Clics sur le lien unique swapiles.com/app (bio Instagram, affiches…), anonymes. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_lien_clics')) {
            Schema::create('app_lien_clics', function (Blueprint $table) {
                $table->id();
                $table->string('plateforme', 10);
                $table->string('source', 40)->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_lien_clics');
    }
};
