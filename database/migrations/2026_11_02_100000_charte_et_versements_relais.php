<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Charte des points relais (acceptation, mandat d'autofacturation), infos de
 * facturation du commerce, délai de garde des colis et versements mensuels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relay_points', function (Blueprint $table) {
            $table->string('charte_version', 20)->nullable();
            $table->timestamp('charte_acceptee_at')->nullable();
            $table->foreignId('charte_acceptee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('raison_sociale', 160)->nullable();
            $table->string('siret', 20)->nullable();
            $table->boolean('assujetti_tva')->default(false);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('relay_expired_at')->nullable()->after('relay_collected_at');
            $table->timestamp('relay_returned_at')->nullable()->after('relay_expired_at');
        });

        Schema::create('relay_versements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relay_point_id')->constrained()->cascadeOnDelete();
            $table->date('mois');
            $table->unsignedInteger('colis')->default(0);
            $table->decimal('montant', 10, 2)->default(0);
            $table->string('numero', 40)->unique();
            $table->timestamp('verse_at')->nullable();
            $table->string('reference', 120)->nullable();
            $table->timestamps();
            $table->unique(['relay_point_id', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relay_versements');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['relay_expired_at', 'relay_returned_at']);
        });

        Schema::table('relay_points', function (Blueprint $table) {
            $table->dropConstrainedForeignId('charte_acceptee_par');
            $table->dropColumn(['charte_version', 'charte_acceptee_at', 'raison_sociale', 'siret', 'assujetti_tva']);
        });
    }
};
