<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Nouveau statut d'annonce « live » : exclusivité cachée jusqu'à son dévoilement en direct. */
return new class extends Migration
{
    private const AVANT = ['draft', 'published', 'closed', 'sold', 'vacances'];

    public function up(): void
    {
        $this->statuts([...self::AVANT, 'live']);
    }

    public function down(): void
    {
        DB::table('listings')->where('status', 'live')->update(['status' => 'draft']);
        $this->statuts(self::AVANT);
    }

    private function statuts(array $valeurs): void
    {
        if (DB::getDriverName() === 'mysql') {
            $liste = implode(',', array_map(fn ($v) => "'" . $v . "'", $valeurs));
            DB::statement("ALTER TABLE listings MODIFY status ENUM({$liste}) NOT NULL DEFAULT 'draft'");

            return;
        }

        Schema::table('listings', function (Blueprint $table) use ($valeurs) {
            $table->enum('status', $valeurs)->default('draft')->change();
        });
    }
};
