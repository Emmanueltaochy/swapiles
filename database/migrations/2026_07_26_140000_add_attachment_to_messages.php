<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pièces jointes (photo / vidéo) dans la messagerie : un fichier optionnel par
 * message. Le fichier est stocké sur le disque public ; on garde son chemin,
 * son type (image|video) et son mime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('body');
            }
            if (!Schema::hasColumn('messages', 'attachment_type')) {
                $table->string('attachment_type', 16)->nullable()->after('attachment_path');
            }
            if (!Schema::hasColumn('messages', 'attachment_mime')) {
                $table->string('attachment_mime')->nullable()->after('attachment_type');
            }
        });

        // Un message peut désormais n'être qu'une pièce jointe : body devient nullable.
        Schema::table('messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            foreach (['attachment_path', 'attachment_type', 'attachment_mime'] as $col) {
                if (Schema::hasColumn('messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
