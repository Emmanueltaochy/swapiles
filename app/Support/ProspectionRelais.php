<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\RelaisProspect;
use App\Models\RelayPoint;
use App\Models\RelayPointRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Prospection des points relais : repère les commerces de chaque île sur
 * OpenStreetMap (carte libre, réutilisation autorisée), cherche l'adresse
 * e-mail publiée sur le site du commerce, puis envoie un mail de présentation
 * depuis l'admin. Prospection B2B : chaque mail porte un lien pour ne plus
 * être contacté, et on n'écrit jamais deux fois sans réponse.
 */
class ProspectionRelais
{
    /** Code ISO de chaque île dans OpenStreetMap. */
    public const ILES = [
        'La Réunion' => 'RE',
        'Guadeloupe' => 'GP',
        'Martinique' => 'MQ',
        'Guyane' => 'GF',
        'Mayotte' => 'YT',
    ];

    /** Numéro de département, pour retrouver l'île quel que soit le code posé sur la carte. */
    private const DEPARTEMENTS = ['RE' => '974', 'GP' => '971', 'MQ' => '972', 'GF' => '973', 'YT' => '976'];

    /** Types de commerces qui font de bons points relais (tag OSM => libellé). */
    public const TYPES = [
        'convenience' => 'Supérette / épicerie',
        'supermarket' => 'Supermarché',
        'tobacco' => 'Tabac',
        'newsagent' => 'Presse',
        'kiosk' => 'Kiosque',
        'dry_cleaning' => 'Pressing',
        'laundry' => 'Laverie',
        'mobile_phone' => 'Téléphonie',
        'computer' => 'Informatique',
        'electronics' => 'Électronique',
        'copyshop' => 'Reprographie',
        'florist' => 'Fleuriste',
        'books' => 'Librairie',
        'stationery' => 'Papeterie',
        'gift' => 'Cadeaux',
        'variety_store' => 'Bazar',
        'general' => 'Commerce général',
        'second_hand' => 'Dépôt-vente',
        'charity' => 'Boutique solidaire',
        'toys' => 'Jouets',
        'baby_goods' => 'Puériculture',
        'pet' => 'Animalerie',
        'hardware' => 'Quincaillerie',
        'clothes' => 'Vêtements',
        'shoes' => 'Chaussures',
        'bakery' => 'Boulangerie',
        'fuel' => 'Station-service',
    ];

    /** Envois par jour au plus : la boîte contact@ est plafonnée à 1 000 e-mails/jour pour tout le site. */
    public static function plafondJour(): int
    {
        return (int) config('relais.prospection_par_jour', 40);
    }

    public static function envoyesAujourdhui(): int
    {
        return RelaisProspect::where('contacte_at', '>=', today())->count();
    }

    /**
     * Récupère les commerces d'une île sur OpenStreetMap et les ajoute
     * (sans doublon, sans toucher au suivi des commerces déjà connus).
     *
     * @return array{trouves:int,nouveaux:int}
     */
    public static function importer(string $ile): array
    {
        $code = self::ILES[$ile] ?? null;
        if (! $code) {
            return ['trouves' => 0, 'nouveaux' => 0];
        }

        $shops = implode('|', array_keys(array_diff_key(self::TYPES, ['fuel' => true])));
        $requete = '[out:json][timeout:120];'
            // L'île peut porter son code pays (RE) ou son code de région (FR-974 / FR-RE).
            . '(area["ISO3166-1"="' . $code . '"];area["ISO3166-2"="FR-' . self::DEPARTEMENTS[$code] . '"];area["ISO3166-2"="FR-' . $code . '"];)->.a;'
            . '(nwr["shop"~"^(' . $shops . ')$"]["name"](area.a);nwr["amenity"="fuel"]["name"](area.a););'
            . 'out center tags;';

        $elements = null;
        foreach (['https://overpass-api.de/api/interpreter', 'https://overpass.kumi.systems/api/interpreter'] as $serveur) {
            try {
                $reponse = Http::timeout(150)->asForm()->post($serveur, ['data' => $requete]);
                if ($reponse->successful()) {
                    $elements = $reponse->json('elements');
                    break;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
        if (! is_array($elements)) {
            throw new \RuntimeException('OpenStreetMap ne répond pas pour le moment. Réessayez plus tard.');
        }

        $relaisExistants = RelayPoint::query()->pluck('name')->map(fn ($n) => Str::lower(trim((string) $n)))->all();
        $nouveaux = 0;

        foreach ($elements as $el) {
            $t = $el['tags'] ?? [];
            $nom = trim((string) ($t['name'] ?? ''));
            if ($nom === '') {
                continue;
            }

            $sourceId = 'osm:' . ($el['type'] ?? 'x') . '/' . ($el['id'] ?? 0);
            $type = isset($t['amenity']) && $t['amenity'] === 'fuel' ? 'fuel' : ($t['shop'] ?? null);
            $rue = trim(($t['addr:housenumber'] ?? '') . ' ' . ($t['addr:street'] ?? ''));

            $prospect = RelaisProspect::firstOrNew(['source_id' => $sourceId]);
            $nouveau = ! $prospect->exists;
            $prospect->fill([
                'nom' => Str::limit($nom, 157, '…'),
                'type' => $type,
                'adresse' => $rue !== '' ? Str::limit($rue, 250, '') : $prospect->adresse,
                'code_postal' => $t['addr:postcode'] ?? $prospect->code_postal,
                'ville' => $t['addr:city'] ?? $prospect->ville,
                'territoire' => $ile,
                'latitude' => $el['lat'] ?? ($el['center']['lat'] ?? null),
                'longitude' => $el['lon'] ?? ($el['center']['lon'] ?? null),
                'telephone' => $t['phone'] ?? $t['contact:phone'] ?? $prospect->telephone,
                'site_web' => $t['website'] ?? $t['contact:website'] ?? $prospect->site_web,
                'horaires' => isset($t['opening_hours']) ? Str::limit($t['opening_hours'], 250, '') : $prospect->horaires,
            ]);
            // Un e-mail déjà trouvé ou corrigé à la main n'est pas écrasé.
            $email = self::emailValide($t['email'] ?? $t['contact:email'] ?? null);
            if ($email && blank($prospect->email)) {
                $prospect->email = $email;
            }
            if ($nouveau && in_array(Str::lower($nom), $relaisExistants, true)) {
                $prospect->statut = 'relais';
            }
            $prospect->save();
            $nouveaux += $nouveau ? 1 : 0;
        }

        return ['trouves' => count($elements), 'nouveaux' => $nouveaux];
    }

    /**
     * Cherche l'e-mail publié sur le site des commerces qui n'en ont pas encore.
     *
     * @return int nombre d'e-mails trouvés
     */
    public static function chercherEmails(int $max = 150): int
    {
        $trouves = 0;
        $prospects = RelaisProspect::query()
            ->whereNull('email')->whereNotNull('site_web')->whereNull('email_cherche_at')
            ->where('statut', 'nouveau')
            ->limit($max)->get();

        foreach ($prospects as $p) {
            $email = self::emailDuSite((string) $p->site_web);
            $p->forceFill(['email_cherche_at' => now(), 'email' => $email ?? $p->email])->save();
            $trouves += $email ? 1 : 0;
        }

        return $trouves;
    }

    /** E-mail trouvé sur la page d'accueil du site, ou sur sa page contact. */
    public static function emailDuSite(string $site): ?string
    {
        $site = trim($site);
        if ($site === '') {
            return null;
        }
        if (! Str::startsWith($site, ['http://', 'https://'])) {
            $site = 'https://' . $site;
        }
        // Réseaux sociaux : pas d'adresse lisible sans compte.
        $hote = (string) parse_url($site, PHP_URL_HOST);
        if ($hote === '' || Str::contains($hote, ['facebook.', 'instagram.', 'fb.com', 'linktr.ee'])) {
            return null;
        }

        $base = rtrim(preg_replace('#^(https?://[^/]+).*$#', '$1', $site), '/');
        foreach (array_unique([$site, $base . '/contact', $base . '/nous-contacter']) as $page) {
            try {
                $html = Http::timeout(8)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SwapilesBot/1.0; +https://swapiles.com)'])
                    ->get($page)->body();
            } catch (Throwable) {
                continue;
            }
            $html = html_entity_decode(str_ireplace(['[at]', '(at)', ' arobase '], '@', $html));
            if (preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $html, $m)) {
                foreach ($m[0] as $candidat) {
                    if ($email = self::emailValide($candidat)) {
                        return $email;
                    }
                }
            }
        }

        return null;
    }

    public static function emailValide(?string $email): ?string
    {
        $email = Str::lower(trim((string) $email, " \t\n\r\0\x0B.;,"));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        // Faux positifs fréquents : images, adresses d'exemple, prestataires du site.
        if (preg_match('/\.(png|jpe?g|gif|webp|svg)$/', $email)
            || Str::contains($email, ['example.', 'exemple.', 'domain.', 'sentry', 'wixpress', '@wix', 'godaddy', 'noreply', 'no-reply', 'u003e'])) {
            return null;
        }

        return $email;
    }

    /**
     * Demande par ville : annonces de la ville + demandes « un relais près de
     * chez moi ». Sert à contacter d'abord là où des ventes se perdent.
     *
     * @return array<string,int> ville en minuscules => poids
     */
    public static function demandeParVille(): array
    {
        $poids = [];
        Listing::query()->where('status', 'published')->whereNotNull('pickup_city')
            ->selectRaw('LOWER(pickup_city) as v, COUNT(*) as n')->groupBy('v')->pluck('n', 'v')
            ->each(function ($n, $v) use (&$poids) { $poids[trim((string) $v)] = ($poids[trim((string) $v)] ?? 0) + (int) $n; });
        RelayPointRequest::query()->whereNotNull('city')
            ->selectRaw('LOWER(city) as v, COUNT(*) as n')->groupBy('v')->pluck('n', 'v')
            // Une demande explicite pèse comme 20 annonces.
            ->each(function ($n, $v) use (&$poids) { $poids[trim((string) $v)] = ($poids[trim((string) $v)] ?? 0) + 20 * (int) $n; });

        return $poids;
    }

    /** Envoie le mail de présentation. False si non contactable ou plafond du jour atteint. */
    public static function envoyer(RelaisProspect $p): bool
    {
        if (blank($p->email) || $p->statut !== 'nouveau' || self::envoyesAujourdhui() >= self::plafondJour()) {
            return false;
        }

        $donnees = [
            'p' => $p,
            'commission' => (float) config('pricing.relay_merchant_fee', 1.00),
            'garde' => (int) config('relais.delai_garde_jours', 10),
            'lienPartenaire' => route('relay.partner'),
            'lienDesinscription' => route('relais.prospection.desinscription', $p->jeton),
            'contact' => config('partenaires.contact'),
        ];

        Mail::send('emails.prospection-relais', $donnees, function ($mail) use ($p) {
            $mail->from('contact@swapiles.com', "Swap'Îles")
                ->replyTo('contact@swapiles.com', "Emmanuel Taochy (Swap'Îles)")
                ->to($p->email, $p->nom)
                ->subject('Devenir point relais Swap’Îles à ' . ($p->ville ?: $p->territoire) . ' : des clients en plus, sans investissement');
        });

        $p->forceFill(['statut' => 'contacte', 'contacte_at' => now(), 'nb_envois' => $p->nb_envois + 1])->save();

        return true;
    }
}
