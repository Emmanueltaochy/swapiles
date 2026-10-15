<?php

namespace App\Console\Commands;

use App\Support\LiveEnregistrement;
use Illuminate\Console\Command;

class CleanLiveReplays extends Command
{
    protected $signature = 'live:nettoyer-replays';

    protected $description = 'Efface du stockage la vidéo des replays supprimés';

    public function handle(): int
    {
        $this->info(LiveEnregistrement::nettoyer() . ' replay(s) effacé(s).');

        return self::SUCCESS;
    }
}
