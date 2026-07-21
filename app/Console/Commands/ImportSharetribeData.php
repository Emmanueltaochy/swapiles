<?php

namespace App\Console\Commands;

use App\Jobs\DownloadSharetribeImageJob;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Message;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportSharetribeData extends Command
{
    protected $signature = 'sharetribe:import
                            {file : Chemin absolu vers le fichier JSON Sharetribe}
                            {--dry-run : Simule l\'import sans rien écrire en base}
                            {--only= : Importer uniquement un type d\'entité (users|listings|transactions|messages)}
                            {--skip-images : Ne pas dispatcher les jobs de téléchargement d\'images}';

    protected $description = 'Importe les données Sharetribe (JSON unique) dans la base MySQL Laravel';

    /** Compteurs pour le rapport final */
    private array $stats = [
        'users'        => ['imported' => 0, 'skipped' => 0, 'failed' => 0],
        'listings'     => ['imported' => 0, 'skipped' => 0, 'failed' => 0],
        'images'       => ['imported' => 0, 'skipped' => 0, 'failed' => 0],
        'transactions' => ['imported' => 0, 'skipped' => 0, 'failed' => 0],
        'messages'     => ['imported' => 0, 'skipped' => 0, 'failed' => 0],
    ];

    /** Erreurs détaillées pour le rapport */
    private array $errors = [];

    /** Mode dry-run ? */
    private bool $dryRun = false;

    /** URL de base du CDN Sharetribe pour reconstruire les URLs d'images */
    private const SHARETRIBE_CDN_BASE = 'https://sharetribe.imgix.net/';

    public function handle(): int
    {
        $file = $this->argument('file');
        $this->dryRun = (bool) $this->option('dry-run');
        $only = $this->option('only');

        if (! file_exists($file)) {
            $this->error("Fichier introuvable : {$file}");
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('━━━ MODE DRY-RUN : aucune écriture en base ━━━');
        }

        $this->info("Lecture du fichier : {$file}");
        $this->info('Taille : '.$this->humanFilesize(filesize($file)));

        // Chargement du JSON. Pour <100Mo c'est OK en mémoire.
        // Au-delà il faudrait passer par halaxa/json-machine en streaming.
        $raw = file_get_contents($file);
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            $this->error('JSON invalide ou vide.');
            return self::FAILURE;
        }

        $this->info('Nombre total d\'objets dans le JSON : '.count($data));

        // Tri par type d'entité (le JSON est un tableau mélangé)
        $sorted = ['user' => [], 'listing' => [], 'transaction' => [], 'stockReservation' => []];
        foreach ($data as $item) {
            if (isset($item['type']) && isset($sorted[$item['type']])) {
                $sorted[$item['type']][] = $item;
            }
        }

        $this->info('  → Users : '.count($sorted['user']));
        $this->info('  → Listings : '.count($sorted['listing']));
        $this->info('  → Transactions : '.count($sorted['transaction']));
        $this->info('  → StockReservations : '.count($sorted['stockReservation']).' (ignorés, pas de table dédiée)');
        $this->newLine();

        // Ordre d'import strict pour respecter les dépendances FK
        // 1. Users (les listings y font référence)
        // 2. Listings (les transactions y font référence)
        // 3. Listing images (dépendent des listings)
        // 4. Transactions (dépendent de users + listings)
        // 5. Messages (imbriqués dans les transactions)

        if (! $only || $only === 'users') {
            $this->importUsers($sorted['user']);
        }

        if (! $only || $only === 'listings') {
            $this->importListings($sorted['listing']);
        }

        if (! $only || $only === 'transactions') {
            $this->importTransactions($sorted['transaction']);
        }

        $this->printReport();

        return self::SUCCESS;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // USERS
    // ═══════════════════════════════════════════════════════════════════════

    private function importUsers(array $users): void
    {
        $this->info('━━━ Import des USERS ━━━');
        $bar = $this->output->createProgressBar(count($users));
        $bar->start();

        foreach ($users as $u) {
            try {
                $uuid = $u['id'] ?? null;
                $attrs = $u['attributes'] ?? [];
                $profile = $attrs['profile'] ?? [];

                if (! $uuid || empty($attrs['email'])) {
                    $this->stats['users']['failed']++;
                    $this->errors[] = "User sans UUID ou sans email : ".json_encode($u);
                    $bar->advance();
                    continue;
                }

                // Idempotence : si déjà importé, on skip
                if ($this->alreadyImported('user', $uuid)) {
                    $this->stats['users']['skipped']++;
                    $bar->advance();
                    continue;
                }

                // Vérifie aussi par email (un user pourrait exister sans mapping)
                $existing = User::where('email', $attrs['email'])->first();
                if ($existing) {
                    if (! $this->dryRun) {
                        $this->recordMapping('user', $uuid, $existing->id);
                    }
                    $this->stats['users']['skipped']++;
                    $bar->advance();
                    continue;
                }

                $firstName = $profile['firstName'] ?? '';
                $lastName  = $profile['lastName'] ?? '';
                $displayName = $profile['displayName'] ?? trim("{$firstName} {$lastName}") ?: 'Utilisateur';

                $userData = [
                    'name'              => $displayName,
                    'email'             => $attrs['email'],
                    'email_verified_at' => ($attrs['emailVerified'] ?? false) ? Carbon::parse($attrs['createdAt']) : null,
                    'password'          => bcrypt(Str::random(40)), // mdp aléatoire, magic link à la 1re connexion
                    'sharetribe_id'     => $uuid,
                    'stripe_account_id' => $attrs['stripeAccountId'] ?? null,
                    'is_banned'         => $attrs['banned'] ?? false,
                    'territoire'        => 'la-reunion', // valeur par défaut, sera affinée plus tard
                    'created_at'        => Carbon::parse($attrs['createdAt']),
                    'updated_at'        => Carbon::parse($attrs['createdAt']),
                ];

                if (! $this->dryRun) {
                    $user = User::create($userData);
                    $this->recordMapping('user', $uuid, $user->id, $u);
                }

                $this->stats['users']['imported']++;

            } catch (\Throwable $e) {
                $this->stats['users']['failed']++;
                $this->errors[] = "User {$uuid} : ".$e->getMessage();
                Log::error('Sharetribe import user failed', ['uuid' => $uuid ?? null, 'error' => $e->getMessage()]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LISTINGS
    // ═══════════════════════════════════════════════════════════════════════

    private function importListings(array $listings): void
    {
        $this->info('━━━ Import des LISTINGS ━━━');
        $bar = $this->output->createProgressBar(count($listings));
        $bar->start();

        foreach ($listings as $l) {
            try {
                $uuid = $l['id'] ?? null;
                $attrs = $l['attributes'] ?? [];
                $publicData = $attrs['publicData'] ?? [];

                if (! $uuid) {
                    $this->stats['listings']['failed']++;
                    $bar->advance();
                    continue;
                }

                if ($this->alreadyImported('listing', $uuid)) {
                    $this->stats['listings']['skipped']++;
                    $bar->advance();
                    continue;
                }

                // Résolution de l'auteur (vendeur)
                $authorUuid = $attrs['author'] ?? null;
                $sellerId = $this->resolveLocalId('user', $authorUuid);

                if (! $sellerId) {
                    $this->stats['listings']['failed']++;
                    $this->errors[] = "Listing {$uuid} : auteur introuvable (UUID {$authorUuid})";
                    $bar->advance();
                    continue;
                }

                // Mapping listing_type Sharetribe → enum Laravel
                $listingType = $this->mapListingType($publicData['listingType'] ?? 'achat');

                // Mapping status
                $status = $this->mapListingStatus($attrs['state'] ?? 'draft');

                // Prix : Sharetribe envoie en EUR avec décimales (ex: 15.00)
                // Ta table attend un INT — on stocke donc en CENTIMES pour ne perdre aucune précision
                $priceAmount = $attrs['price']['amount'] ?? 0;
                $priceInCents = (int) round($priceAmount * 100);

                $shippingPriceCents = $publicData['shippingPriceInSubunitsOneItem'] ?? 0;

                $listingData = [
                    'sharetribe_id'    => $uuid,
                    'user_id'          => $sellerId,
                    'title'            => $this->truncate($attrs['title'] ?? 'Sans titre', 255),
                    'description'     => $attrs['description'] ?? null,
                    'price'            => $priceInCents,
                    'currency'         => $attrs['price']['currency'] ?? 'EUR',
                    'listing_type'     => $listingType,
                    'status'           => $status,
                    'territoire'       => $publicData['Territoire'] ?? 'la-reunion',
                    'category_level1'  => $this->normalize($publicData['categoryLevel1'] ?? null),
                    'category_level2'  => $this->normalize($publicData['categoryLevel2'] ?? null),
                    'category_level3'  => $this->normalize($publicData['categoryLevel3'] ?? null),
                    'etat'             => $publicData['etat'] ?? null,
                    'marque'           => $this->truncate($publicData['marque'] ?? null, 255),
                    'taille'           => $publicData['taille'] ?? null,
                    'couleurs'         => ! empty($publicData['Couleurs']) ? json_encode($publicData['Couleurs']) : null,
                    'location_address' => $this->truncate($publicData['location']['address'] ?? null, 255),
                    'pickup_enabled'   => $publicData['pickupEnabled'] ?? true,
                    'shipping_enabled' => $publicData['shippingEnabled'] ?? false,
                    'shipping_price'   => $shippingPriceCents,
                    'created_at'       => Carbon::parse($attrs['createdAt']),
                    'updated_at'       => Carbon::parse($attrs['createdAt']),
                ];

                if (! $this->dryRun) {
                    $listing = Listing::create($listingData);
                    $this->recordMapping('listing', $uuid, $listing->id, $l);

                    // Images : on enregistre les UUIDs Sharetribe comme URLs CDN temporaires
                    // Le job DownloadSharetribeImageJob les téléchargera ensuite en arrière-plan
                    $this->importListingImages($listing, $attrs['images'] ?? []);
                }

                $this->stats['listings']['imported']++;

            } catch (\Throwable $e) {
                $this->stats['listings']['failed']++;
                $this->errors[] = "Listing {$uuid} : ".$e->getMessage();
                Log::error('Sharetribe import listing failed', ['uuid' => $uuid ?? null, 'error' => $e->getMessage()]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    private function importListingImages(Listing $listing, array $imageUuids): void
    {
        foreach ($imageUuids as $order => $imageUuid) {
            if ($this->alreadyImported('image', $imageUuid)) {
                $this->stats['images']['skipped']++;
                continue;
            }

            // URL temporaire reconstruite depuis le CDN Sharetribe
            // Le job de téléchargement remplacera cette URL par celle locale après téléchargement
            $tempUrl = self::SHARETRIBE_CDN_BASE.$imageUuid;

            $image = ListingImage::create([
                'listing_id' => $listing->id,
                'url'        => $tempUrl,
                'order'      => $order,
            ]);

            $this->recordMapping('image', $imageUuid, $image->id);
            $this->stats['images']['imported']++;

            // Dispatch du job de téléchargement (sauf si désactivé)
            if (! $this->option('skip-images')) {
                DownloadSharetribeImageJob::dispatch($image->id, $imageUuid);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TRANSACTIONS + MESSAGES (imbriqués)
    // ═══════════════════════════════════════════════════════════════════════

    private function importTransactions(array $transactions): void
    {
        $this->info('━━━ Import des TRANSACTIONS et MESSAGES ━━━');
        $bar = $this->output->createProgressBar(count($transactions));
        $bar->start();

        foreach ($transactions as $t) {
            try {
                $uuid = $t['id'] ?? null;
                $attrs = $t['attributes'] ?? [];

                if (! $uuid) {
                    $this->stats['transactions']['failed']++;
                    $bar->advance();
                    continue;
                }

                $listingId = $this->resolveLocalId('listing', $attrs['listingId'] ?? null);
                $sellerId  = $this->resolveLocalId('user', $attrs['providerId'] ?? null);
                $buyerId   = $this->resolveLocalId('user', $attrs['customerId'] ?? null);

                // Si le listing ou les users référencés n'existent pas, on skip proprement
                if (! $listingId || ! $sellerId || ! $buyerId) {
                    $this->stats['transactions']['failed']++;
                    $this->errors[] = "Transaction {$uuid} : référence manquante (listing/seller/buyer)";
                    $bar->advance();
                    continue;
                }

                $transactionLocalId = null;

                if (! $this->alreadyImported('transaction', $uuid)) {
                    $amount = isset($attrs['payinTotal']['amount']) ? (int) round($attrs['payinTotal']['amount'] * 100) : 0;
                    $commission = $this->extractCommission($attrs['lineItems'] ?? []);
                    $paymentMethod = $this->detectPaymentMethod($attrs);
                    $status = $this->mapTransactionStatus($attrs['lastTransition'] ?? null);

                    $txData = [
                        'sharetribe_id'            => $uuid,
                        'listing_id'               => $listingId,
                        'seller_id'                => $sellerId,
                        'buyer_id'                 => $buyerId,
                        'amount'                   => $amount,
                        'commission'               => $commission,
                        'currency'                 => $attrs['payinTotal']['currency'] ?? 'EUR',
                        'payment_method'           => $paymentMethod,
                        'status'                   => $status,
                        'stripe_payment_intent_id' => $this->extractStripePaymentIntent($attrs['payIns'] ?? []),
                        'completed_at'             => $status === 'completed' ? Carbon::parse($attrs['lastTransitionedAt']) : null,
                        'created_at'               => Carbon::parse($attrs['createdAt']),
                        'updated_at'               => Carbon::parse($attrs['lastTransitionedAt'] ?? $attrs['createdAt']),
                    ];

                    if (! $this->dryRun) {
                        $tx = Transaction::create($txData);
                        $transactionLocalId = $tx->id;
                        $this->recordMapping('transaction', $uuid, $tx->id, $t);
                    }

                    $this->stats['transactions']['imported']++;
                } else {
                    $transactionLocalId = $this->resolveLocalId('transaction', $uuid);
                    $this->stats['transactions']['skipped']++;
                }

                // Import des messages imbriqués
                $this->importMessages(
                    $attrs['messages'] ?? [],
                    $listingId,
                    $sellerId,
                    $buyerId,
                    $attrs
                );

                // Premier message : l'inquiryMessage s'il existe
                $inquiryMsg = $attrs['protectedData']['inquiryMessage'] ?? null;
                if ($inquiryMsg) {
                    $this->createMessageIfNew(
                        $uuid.'-inquiry', // pseudo-UUID pour le message initial
                        $listingId,
                        $buyerId, // l'inquiry est envoyée par l'acheteur
                        $sellerId,
                        $inquiryMsg,
                        Carbon::parse($attrs['createdAt'])
                    );
                }

            } catch (\Throwable $e) {
                $this->stats['transactions']['failed']++;
                $this->errors[] = "Transaction {$uuid} : ".$e->getMessage();
                Log::error('Sharetribe import transaction failed', ['uuid' => $uuid ?? null, 'error' => $e->getMessage()]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    private function importMessages(array $messages, int $listingId, int $sellerId, int $buyerId, array $txAttrs): void
    {
        foreach ($messages as $m) {
            $msgUuid = $m['id'] ?? null;
            if (! $msgUuid) continue;

            $senderUuid = $m['sender'] ?? null;
            $senderId = $this->resolveLocalId('user', $senderUuid);

            if (! $senderId) {
                $this->stats['messages']['failed']++;
                $this->errors[] = "Message {$msgUuid} : sender introuvable";
                continue;
            }

            // Le destinataire est l'autre partie de la transaction
            $receiverId = $senderId === $sellerId ? $buyerId : $sellerId;

            $this->createMessageIfNew(
                $msgUuid,
                $listingId,
                $senderId,
                $receiverId,
                $m['content'] ?? '',
                Carbon::parse($m['createdAt'])
            );
        }
    }

    private function createMessageIfNew(string $uuid, int $listingId, int $senderId, int $receiverId, string $body, Carbon $createdAt): void
    {
        if ($this->alreadyImported('message', $uuid)) {
            $this->stats['messages']['skipped']++;
            return;
        }

        if (empty(trim($body))) {
            return; // message vide, on skip
        }

        if (! $this->dryRun) {
            $msg = Message::create([
                'sharetribe_id' => $uuid,
                'listing_id'    => $listingId,
                'sender_id'     => $senderId,
                'receiver_id'   => $receiverId,
                'body'          => $body,
                'created_at'    => $createdAt,
                'updated_at'    => $createdAt,
            ]);

            $this->recordMapping('message', $uuid, $msg->id);
        }

        $this->stats['messages']['imported']++;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPERS : mapping, résolution, normalisation
    // ═══════════════════════════════════════════════════════════════════════

    private function alreadyImported(string $type, string $uuid): bool
    {
        if ($this->dryRun) return false;
        return DB::table('sharetribe_imports')
            ->where('entity_type', $type)
            ->where('external_id', $uuid)
            ->exists();
    }

    private function resolveLocalId(string $type, ?string $uuid): ?int
    {
        if (! $uuid) return null;
        if ($this->dryRun) return 1; // valeur factice pour passer les checks en dry-run

        $row = DB::table('sharetribe_imports')
            ->where('entity_type', $type)
            ->where('external_id', $uuid)
            ->first();

        return $row?->local_id;
    }

    private function recordMapping(string $type, string $uuid, int $localId, ?array $payload = null): void
    {
        DB::table('sharetribe_imports')->insert([
            'entity_type' => $type,
            'external_id' => $uuid,
            'local_id'    => $localId,
            'payload'     => $payload ? json_encode($payload) : null,
            'imported_at' => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function mapListingType(string $type): string
    {
        // Mapping Sharetribe → enum Laravel
        return match (strtolower($type)) {
            'achat'                            => 'achat',
            'echange-produits', 'echange'      => 'echange-produits',
            'don'                              => 'don',
            'location-vetements', 'location'   => 'location-vetements',
            'negoce-prix'                      => 'negoce-prix',
            default                            => 'achat',
        };
    }

    private function mapListingStatus(string $state): string
    {
        return match (strtolower($state)) {
            'published' => 'published',
            'closed'    => 'closed',
            'draft'     => 'draft',
            default     => 'draft',
        };
    }

    private function mapTransactionStatus(?string $transition): string
    {
        if (! $transition) return 'inquiry';

        // Les transitions Sharetribe qui indiquent un paiement complété
        if (str_contains($transition, 'expire-review-period')) return 'completed';
        if (str_contains($transition, 'complete')) return 'completed';
        if (str_contains($transition, 'cancel')) return 'cancelled';
        if (str_contains($transition, 'refund')) return 'refunded';
        if (str_contains($transition, 'withdraw')) return 'cancelled';
        if (str_contains($transition, 'inquire')) return 'inquiry';
        if (str_contains($transition, 'pay')) return 'paid';

        return 'pending';
    }

    private function detectPaymentMethod(array $attrs): string
    {
        // Si paiement Stripe effectif → CB
        if (! empty($attrs['payIns'])) {
            foreach ($attrs['payIns'] as $payIn) {
                if (! empty($payIn['stripeChargeId'])) {
                    return 'cb';
                }
            }
        }

        // Pas de paiement → espèces par défaut (réalité du terrain)
        // Tu pourras affiner manuellement les "echange" et "don" via Filament après
        return 'especes';
    }

    private function extractCommission(array $lineItems): int
    {
        foreach ($lineItems as $item) {
            if (($item['code'] ?? '') === 'line-item/provider-commission') {
                $amount = $item['lineTotal']['amount'] ?? 0;
                return (int) round(abs($amount) * 100); // valeur absolue en centimes
            }
        }
        return 0;
    }

    private function extractStripePaymentIntent(array $payIns): ?string
    {
        foreach ($payIns as $payIn) {
            if (! empty($payIn['stripePaymentIntentId'])) {
                return $payIn['stripePaymentIntentId'];
            }
        }
        return null;
    }

    private function normalize(?string $value): ?string
    {
        if (! $value) return null;
        // Normalisation : trim + lowercase pour cohérence des catégories
        return Str::slug(trim($value), '-');
    }

    private function truncate(?string $value, int $max): ?string
    {
        if (! $value) return $value;
        return mb_substr($value, 0, $max);
    }

    private function humanFilesize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2).' '.$units[$i];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RAPPORT FINAL
    // ═══════════════════════════════════════════════════════════════════════

    private function printReport(): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  RAPPORT D\'IMPORT SHARETRIBE');
        $this->info('═══════════════════════════════════════════════════');

        $rows = [];
        foreach ($this->stats as $type => $counts) {
            $rows[] = [
                ucfirst($type),
                $counts['imported'],
                $counts['skipped'],
                $counts['failed'],
            ];
        }

        $this->table(
            ['Type', 'Importés', 'Déjà présents (skip)', 'Échecs'],
            $rows
        );

        if (! empty($this->errors)) {
            $this->newLine();
            $this->warn('━━━ ERREURS DÉTAILLÉES ('.count($this->errors).') ━━━');
            $logFile = storage_path('logs/sharetribe-import-'.date('Y-m-d-His').'.log');
            file_put_contents($logFile, implode(PHP_EOL, $this->errors));
            $this->warn("Log complet : {$logFile}");
            $this->newLine();

            // Affiche les 10 premières erreurs en console
            foreach (array_slice($this->errors, 0, 10) as $err) {
                $this->line('  • '.$err);
            }
            if (count($this->errors) > 10) {
                $this->line('  ... et '.(count($this->errors) - 10).' autres (voir le log)');
            }
        }

        if ($this->dryRun) {
            $this->newLine();
            $this->warn('━━━ DRY-RUN : aucune donnée n\'a été écrite. Relance sans --dry-run pour exécuter. ━━━');
        }
    }
}
