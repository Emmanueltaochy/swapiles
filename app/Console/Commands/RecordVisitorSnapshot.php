<?php

namespace App\Console\Commands;

use App\Models\LiveVisit;
use App\Models\VisitorSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RecordVisitorSnapshot extends Command
{
    protected $signature = 'traffic:snapshot';

    protected $description = 'Enregistre le nombre de visiteurs connectés (pour la courbe de fréquentation).';

    public function handle(): int
    {
        if (! Schema::hasTable('visitor_snapshots') || ! Schema::hasTable('live_visits')) {
            return self::SUCCESS;
        }

        $live = LiveVisit::query()
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->count();

        $members = LiveVisit::query()
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');

        VisitorSnapshot::create([
            'live_count' => $live,
            'members_count' => $members,
            'created_at' => now(),
        ]);

        return self::SUCCESS;
    }
}
