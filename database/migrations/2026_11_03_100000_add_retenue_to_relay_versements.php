<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Charte : colis perdu par la faute du commerçant => retenue sur le versement du mois. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relay_versements', function (Blueprint $table) {
            $table->decimal('retenue', 10, 2)->default(0)->after('montant');
            $table->string('retenue_motif', 255)->nullable()->after('retenue');
        });
    }

    public function down(): void
    {
        Schema::table('relay_versements', function (Blueprint $table) {
            $table->dropColumn(['retenue', 'retenue_motif']);
        });
    }
};
