<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Points relais tenus par une enseigne partenaire (RSE) : nom, logo, rapport d'impact. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relay_points', function (Blueprint $table) {
            if (! Schema::hasColumn('relay_points', 'partenaire_enseigne')) {
                $table->string('partenaire_enseigne', 120)->nullable();
                $table->string('partenaire_logo')->nullable();
                $table->date('partenaire_depuis')->nullable();
                $table->string('rapport_jeton', 40)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('relay_points', function (Blueprint $table) {
            $table->dropColumn(['partenaire_enseigne', 'partenaire_logo', 'partenaire_depuis', 'rapport_jeton']);
        });
    }
};
