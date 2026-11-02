<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fenêtre de notation intégrée à l'appli (App Store / Google Play), proposée
 * après un moment heureux (avis 4-5 étoiles). Voir App\Support\NoteAppli.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Moment heureux noté : la fenêtre s'ouvrira à la prochaine visite dans l'appli.
            $table->timestamp('note_native_due_at')->nullable();
            // Dernière fois que la fenêtre a été proposée.
            $table->timestamp('note_native_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['note_native_due_at', 'note_native_at']);
        });
    }
};
