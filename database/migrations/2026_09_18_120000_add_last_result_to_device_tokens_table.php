<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Résultat du dernier envoi push, par appareil.
 *
 * Sans ça, un échec d'envoi n'apparaissait que dans les journaux du serveur :
 * invisible depuis l'administration, donc impossible à diagnostiquer sans accès
 * SSH. On conserve désormais le résultat et le message d'erreur exact renvoyé
 * par Apple ou Google.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('last_result', 20)->nullable();   // ok | invalid | error | skipped
            $table->text('last_error')->nullable();          // message exact du service
            $table->timestamp('last_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropColumn(['last_result', 'last_error', 'last_sent_at']);
        });
    }
};
