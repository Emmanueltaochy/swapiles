<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Scrape les URLs d'images signées depuis l'ancien site Sharetribe public.
 *
 * Sharetribe met TOUTES les images d'une annonce dans des balises meta og:image
 * (une par photo, avec data-rh="true" comme attribut React Helmet). Ce script
 * extrait chacune de ces URLs signées et synchronise les enregistrements
 * listing_images en base.
 *
 * Idempotent : peut être relancé sans dupliquer.
 */
class ScrapeSharetribeImageUrls extends Command
{
    protected $signature = 'sharetribe:scrape-images
                            {--base-url=https://swapiles.mysharetribe.com : URL de base du site Sharetribe public}
                            {--delay=1 : Délai en secondes entre chaque requête (rate limiting)}
                            {--limit= : Limite le nombre de listings traités (utile pour tester)}
                            {--dry-run : Simule sans modifier la base}';

    protected $description = 'Récupère les URLs d\'images signées depuis le site Sharetribe public et met à jour la BDD';

    private array $stats = [
        'listings_processed'  => 0,
        'listings_updated'    => 0,
        'listings_failed'     => 0,
        'listings_no_image'   => 0,
        'images_total_found'  => 0,
        'images_updated'      => 0,
        'images_inserted'     => 0,
        'images_deleted'      => 0,
    ];

    private array $errors = [];

    public function handle(): int
    {
        $baseUrl = rtrim($this->option('base-url'), '/');
        $delay   = (int) $this->option('delay');
        $limit   = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun  = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('━━━ MODE DRY-RUN : aucune modification en base ━━━');
        }

        $this->info("URL de base : {$baseUrl}");
        $this->info("Délai entre requêtes : {$delay}s");
        $this->newLine();

        $query = Listing::whereNotNull('sharetribe_id')
            ->whereHas('images')
            ->with('images');

        if ($limit) {
            $query->limit($limit);
        }

        $total = $query->count();
        $this->info("Listings à traiter : {$total}");
        $this->newLine();

        if ($total === 0) {
            $this->warn('Aucun listing à traiter.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% • %message%');
        $bar->setMessage('Démarrage...');
        $bar->start();

        $query->chunk(50, function ($listings) use ($baseUrl, $delay, $dryRun, $bar) {
            foreach ($listings as $listing) {
                $this->stats['listings_processed']++;
                $shortTitle = mb_substr($listing->title, 0, 40);
                $bar->setMessage("#{$listing->id} : {$shortTitle}");

                try {
                    $imageUrls = $this->fetchAllImageUrlsFromPage($baseUrl, $listing->sharetribe_id);

                    if (empty($imageUrls)) {
                        $this->stats['listings_no_image']++;
                        $this->errors[] = "Listing #{$listing->id} ({$listing->sharetribe_id}) : aucune image trouvée sur la page";
                        $bar->advance();
                        sleep($delay);
                        continue;
                    }

                    $this->stats['images_total_found'] += count($imageUrls);

                    if (! $dryRun) {
                        $this->syncListingImages($listing, $imageUrls);
                    }

                    $this->stats['listings_updated']++;

                } catch (\Throwable $e) {
                    $this->stats['listings_failed']++;
                    $this->errors[] = "Listing #{$listing->id} : ".$e->getMessage();
                    Log::error('Sharetribe scrape error', [
                        'listing_id'    => $listing->id,
                        'sharetribe_id' => $listing->sharetribe_id,
                        'error'         => $e->getMessage(),
                    ]);
                }

                $bar->advance();
                sleep($delay);
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->printReport();

        return self::SUCCESS;
    }

    /**
     * Récupère TOUTES les URLs d'images signées d'une annonce Sharetribe.
     *
     * Sharetribe rend le HTML via React Helmet, ce qui ajoute data-rh="true"
     * sur toutes les balises meta. Pattern souple qui matche dans n'importe
     * quel ordre d'attributs.
     */
    private function fetchAllImageUrlsFromPage(string $baseUrl, string $listingUuid): array
    {
        $url = "{$baseUrl}/l/{$listingUuid}";

        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; SwapilesMigrationBot/1.0)',
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} sur {$url}");
        }

        $html = $response->body();

        $urls = [];

        // Pattern 1 : property="og:image" puis content="..."
        $pattern1 = '/<meta\b[^>]*\bproperty=["\']og:image["\'][^>]*\bcontent=["\']([^"\']*sharetribe\.imgix\.net[^"\']*)["\'][^>]*>/i';
        if (preg_match_all($pattern1, $html, $matches)) {
            foreach ($matches[1] as $u) {
                $urls[] = html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        // Pattern 2 : content="..." puis property="og:image" (ordre inverse)
        $pattern2 = '/<meta\b[^>]*\bcontent=["\']([^"\']*sharetribe\.imgix\.net[^"\']*)["\'][^>]*\bproperty=["\']og:image["\'][^>]*>/i';
        if (preg_match_all($pattern2, $html, $matches)) {
            foreach ($matches[1] as $u) {
                $urls[] = html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        // Déduplication en gardant l'ordre
        
        // Garde uniquement la meilleure version de chaque image Sharetribe :
        // on évite og:image 1200x630 et on privilégie les grandes images produit.
        $best = [];

        foreach (array_values(array_unique($urls)) as $u) {
            $path = parse_url($u, PHP_URL_PATH) ?: $u;
            $key = basename($path);

            parse_str(parse_url($u, PHP_URL_QUERY) ?? '', $q);

            $w = isset($q['w']) ? (int) $q['w'] : 0;
            $h = isset($q['h']) ? (int) $q['h'] : 0;
            $area = $w * $h;

            if (!isset($best[$key]) || $area > $best[$key]['area']) {
                $best[$key] = [
                    'url' => $u,
                    'area' => $area,
                ];
            }
        }

        return array_values(array_map(fn ($item) => $item['url'], $best));

    }

    /**
     * Synchronise les images d'un listing avec les URLs récupérées.
     *
     * Stratégie :
     * - Met à jour les images existantes (jusqu'au min des deux)
     * - Crée de nouveaux enregistrements si on en a trouvé plus
     * - Supprime les enregistrements en trop si on en a trouvé moins
     */
    private function syncListingImages(Listing $listing, array $newUrls): void
    {
        DB::transaction(function () use ($listing, $newUrls) {
            $existingImages = $listing->images()->orderBy('order')->orderBy('id')->get();
            $existingCount = $existingImages->count();
            $newCount = count($newUrls);

            $updateCount = min($existingCount, $newCount);
            for ($i = 0; $i < $updateCount; $i++) {
                $existingImages[$i]->update([
                    'url'   => $newUrls[$i],
                    'order' => $i,
                ]);
                $this->stats['images_updated']++;
            }

            if ($newCount > $existingCount) {
                for ($i = $existingCount; $i < $newCount; $i++) {
                    ListingImage::create([
                        'listing_id' => $listing->id,
                        'url'        => $newUrls[$i],
                        'order'      => $i,
                    ]);
                    $this->stats['images_inserted']++;
                }
            }

            if ($newCount < $existingCount) {
                $toDelete = $existingImages->slice($newCount);
                foreach ($toDelete as $img) {
                    $img->delete();
                    $this->stats['images_deleted']++;
                }
            }
        });
    }

    private function printReport(): void
    {
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  RAPPORT DE SCRAPING');
        $this->info('═══════════════════════════════════════════════════');

        $this->table(
            ['Indicateur', 'Valeur'],
            [
                ['Listings traités',                       $this->stats['listings_processed']],
                ['Listings mis à jour',                    $this->stats['listings_updated']],
                ['Listings sans image trouvée',            $this->stats['listings_no_image']],
                ['Listings en échec',                      $this->stats['listings_failed']],
                ['Total images trouvées sur Sharetribe',   $this->stats['images_total_found']],
                ['Images mises à jour en base',            $this->stats['images_updated']],
                ['Images créées en base',                  $this->stats['images_inserted']],
                ['Images supprimées (orphelines)',         $this->stats['images_deleted']],
            ]
        );

        if (! empty($this->errors)) {
            $this->newLine();
            $this->warn('━━━ ERREURS ('.count($this->errors).') ━━━');
            $logFile = storage_path('logs/sharetribe-scrape-'.date('Y-m-d-His').'.log');
            file_put_contents($logFile, implode(PHP_EOL, $this->errors));
            $this->warn("Log complet : {$logFile}");
            $this->newLine();

            foreach (array_slice($this->errors, 0, 10) as $err) {
                $this->line('  • '.$err);
            }
            if (count($this->errors) > 10) {
                $this->line('  ... et '.(count($this->errors) - 10).' autres (voir le log)');
            }
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('━━━ DRY-RUN : aucune donnée n\'a été écrite. ━━━');
        }
    }
}
