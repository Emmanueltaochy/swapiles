<?php

namespace App\Console\Commands;

use App\Models\VisitorSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RecordVisitorSnapshot extends Command
{
    protected $signature = 'traffic:snapshot';

    protected $description = 'Enregistre le nombre de visiteurs connectés (pour la courbe de fréquentation).';

    public function handle(): int
    {
        if (! Schema::hasTable('visitor_snapshots') || ! Schema::hasTable('presences')) {
            return self::SUCCESS;
        }

        // Humains confirmés par leur navigateur, comme « Visiteurs en direct » :
        // le trafic brut du serveur comptait aussi les robots.
        $enLigne = \App\Support\VisiteursEnDirect::compte();
        $live = $enLigne['humains'];
        $members = $enLigne['membres'];

        VisitorSnapshot::create([
            'live_count' => $live,
            'members_count' => $members,
            'created_at' => now(),
        ]);

        return self::SUCCESS;
    }
}
