<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Marque une annonce masquée par nous pour absence de photo (et évite
            // de renvoyer plusieurs fois la notification / l'e-mail au vendeur).
            $table->timestamp('photoless_hidden_at')->nullable()->after('views_count');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('photoless_hidden_at');
        });
    }
};
