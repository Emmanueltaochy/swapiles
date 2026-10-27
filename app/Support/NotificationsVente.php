<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Notification;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * Notifications dans l'appli (et push) liées au parcours d'une vente :
 * colis parti, colis au point relais, retrait, paiement envoyé, ainsi que
 * les relances (« pense à expédier », « confirme la réception »…).
 *
 * Les e-mails correspondants restent dans SendTransactionStatusEmails ;
 * ici, des messages courts, lisibles sur un écran verrouillé.
 */
class NotificationsVente
{
    /**
     * Relances : jours d'attente avant le 1er puis le 2e rappel (jamais plus).
     * Calés sous les filets de sécurité de transactions:auto-resolve
     * (remboursement à J10 sans expédition, validation auto à J14).
     */
    public const RELANCES = [
        'seller_ship_reminder' => [2, 6],
        'buyer_confirm_reminder' => [4, 9],
        'buyer_handover_reminder' => [2, 5],
        'relay_pickup_reminder' => [2, 7],
        // Vente en main propre déclarée : l'acheteur confirme pour que la vente compte.
        'cash_sale_confirm_reminder' => [2, 6],
    ];

    /** Jours après expédition avant validation automatique (voir AutoResolveTransactions). */
    public const VALIDATION_AUTO_JOURS = 14;

    // --- Étapes de la vente ---------------------------------------------------

    /**
     * Notifications à envoyer après une étape (même vocabulaire que les e-mails).
     *
     * @param  string  $evenement  paid | shipped | relay_deposited | received | released
     * @param  ?string  $par  'commercant' quand c'est le point relais qui a confirmé
     * @return array<int,array{user_id:int,type:string,title:string,message:string,url:string}>
     */
    public static function pourEtape(Transaction $t, string $evenement, ?string $par = null): array
    {
        $titre = $t->listing?->title ?? 'ton article';
        $vente = route('account.transactions.show', $t, absolute: false);
        $relais = $t->relayPoint;
        $commercantId = $relais?->manager_user_id;
        $espaceRelais = route('account.relay.dashboard', absolute: false);
        $vendeur = $t->seller?->name ?? 'Le vendeur';
        $acheteur = $t->buyer?->name ?? "l'acheteur";
        $netVendeur = TableauDeBord::euros((float) ($t->seller_amount ?: $t->amount));
        $ou = $relais ? $relais->name . ($relais->city ? ', ' . $relais->city : '') : 'ton point relais';

        $n = [];
        $ajouter = function (?int $userId, string $type, string $title, string $message, string $url) use (&$n) {
            if ($userId) {
                $n[] = ['user_id' => $userId, 'type' => $type, 'title' => $title, 'message' => $message, 'url' => $url];
            }
        };

        switch ($evenement) {
            case 'paid':
                // Acheteur et vendeur ont déjà leur notification (TransactionPayment).
                if ($t->delivery_method === 'relay') {
                    $ajouter($commercantId, 'relay_incoming', '📦 Un colis va arriver',
                        "{$vendeur} va déposer « {$titre} » chez toi pour {$acheteur}.", $espaceRelais);
                }
                break;

            case 'shipped':
                if ($t->delivery_method !== 'relay') {
                    $suivi = $t->tracking_number ? ' (suivi ' . trim(($t->carrier ? $t->carrier . ' ' : '') . $t->tracking_number) . ')' : '';
                    $ajouter($t->buyer_id, 'transaction_shipped', '📦 Ton colis est parti !',
                        "« {$titre} » est en route{$suivi}. Confirme la réception dès que tu l'as.", $vente);
                }
                break;

            case 'relay_deposited':
                $code = $t->relay_pickup_code ? " Code de retrait : {$t->relay_pickup_code}." : '';
                $ajouter($t->buyer_id, 'relay_ready', "🏪 Ton colis t'attend au point relais",
                    "« {$titre} » est disponible chez {$ou}.{$code}", $vente);

                if ($par === 'commercant') {
                    $ajouter($t->seller_id, 'relay_deposit_confirmed', '✅ Dépôt confirmé',
                        "{$ou} a bien reçu « {$titre} ». {$acheteur} est prévenu.", $vente);
                } else {
                    $ajouter($commercantId, 'relay_deposit_declared', '📦 Colis déposé chez toi',
                        "{$vendeur} a déposé « {$titre} » pour {$acheteur}. Remets-le contre son code de retrait.", $espaceRelais);
                }
                break;

            case 'received':
                $retrait = $t->delivery_method === 'relay';
                $ajouter($t->seller_id, 'transaction_received', '💶 Vente finalisée',
                    "{$acheteur} a " . ($retrait ? 'retiré' : 'bien reçu') . " « {$titre} ». Ton paiement de {$netVendeur} est en route.", $vente);

                if ($par === 'commercant') {
                    $ajouter($t->buyer_id, 'relay_collected', '✅ Colis retiré',
                        "Bonne utilisation de « {$titre} » ! Tu peux laisser un avis à {$vendeur}.", $vente);
                }
                break;

            case 'released':
                $ajouter($t->seller_id, 'payout_sent', '💶 Paiement envoyé',
                    "{$netVendeur} pour « {$titre} » arrivent sur ton compte bancaire (1 à 3 jours ouvrés).", $vente);
                break;
        }

        return $n;
    }

    /** Crée les notifications d'une étape, une seule fois chacune. */
    public static function apresEtape(Transaction $t, string $evenement, ?string $par = null): void
    {
        $t->loadMissing(['listing', 'buyer', 'seller', 'relayPoint']);

        foreach (self::pourEtape($t, $evenement, $par) as $notif) {
            // Le job d'e-mails peut être rejoué : jamais deux fois la même.
            $deja = Notification::where('user_id', $notif['user_id'])
                ->where('type', $notif['type'])
                ->where('url', $notif['url'])
                ->exists();

            if (! $deja) {
                Notification::create($notif);
            }
        }
    }

    // --- Relances ---------------------------------------------------------------

    /**
     * Relance due pour cette vente, ou null.
     *
     * @return array{user_id:int,type:string,title:string,message:string,url:string}|null
     */
    public static function relanceDue(Transaction $t, ?Carbon $maintenant = null): ?array
    {
        $maintenant ??= now();

        if ($t->status !== 'paid' || $t->estDeclaree()) {
            return null;
        }

        $titre = $t->listing?->title ?? 'ton article';
        $vente = route('account.transactions.show', $t, absolute: false);
        $vendeur = $t->seller?->name ?? 'le vendeur';
        $acheteur = $t->buyer?->name ?? "L'acheteur";
        $relais = $t->relayPoint;
        $ou = $relais ? $relais->name . ($relais->city ? ', ' . $relais->city : '') : 'le point relais';

        // [type, destinataire, depuis, titre, message]
        $relance = match (true) {
            // Délai de garde dépassé : plus de relance, l'admin reprend la main.
            $t->delivery_method === 'relay' && in_array($t->relay_status, ['expired', 'returned'], true) => null,
            $t->delivery_method === 'relay' && $t->relay_status === 'deposited' && ! $t->relay_collected_at => [
                'relay_pickup_reminder', $t->buyer_id, $t->relay_deposited_at,
                '🏪 Ton colis t’attend toujours',
                "« {$titre} » est chez {$ou} depuis %d jours." . ($t->relay_pickup_code ? " Code de retrait : {$t->relay_pickup_code}." : '')
                    . ' À retirer avant le ' . GardeRelais::limite($t)?->format('d/m') . ', sinon il sera rendu au vendeur.',
            ],
            $t->delivery_method === 'relay' && ! $t->shipped_at => [
                'seller_ship_reminder', $t->seller_id, $t->created_at,
                '⏰ Pense à déposer ton colis',
                "{$acheteur} attend « {$titre} » depuis %d jours. Dépose-le chez {$ou}.",
            ],
            $t->delivery_method === 'hand_delivery' && ! $t->received_at => [
                'buyer_handover_reminder', $t->buyer_id, $t->created_at,
                '🤝 Tu as récupéré ton article ?',
                "Si tu as bien eu « {$titre} », confirme la remise pour que {$vendeur} soit payé.",
            ],
            $t->delivery_method !== 'hand_delivery' && ! $t->shipped_at => [
                'seller_ship_reminder', $t->seller_id, $t->created_at,
                '⏰ Pense à expédier ton colis',
                "{$acheteur} attend « {$titre} » depuis %d jours. Sans envoi sous 10 jours, la vente peut être remboursée.",
            ],
            $t->delivery_method !== 'hand_delivery' && $t->shipped_at && ! $t->received_at => [
                'buyer_confirm_reminder', $t->buyer_id, $t->shipped_at,
                '📬 Tu as reçu ton colis ?',
                "Confirme la réception de « {$titre} » pour que {$vendeur} soit payé. Sans réponse, la vente sera validée le "
                    . Carbon::parse($t->shipped_at)->addDays(self::VALIDATION_AUTO_JOURS)->format('d/m') . '.',
            ],
            default => null,
        };

        if (! $relance || ! $relance[1] || ! $relance[2]) {
            return null;
        }

        [$type, $destinataire, $depuis, $title, $message] = $relance;
        $jours = (int) Carbon::parse($depuis)->diffInDays($maintenant);

        $dejaEnvoyees = Notification::where('user_id', $destinataire)->where('type', $type)->where('url', $vente)->count();
        $seuils = self::RELANCES[$type];

        if ($dejaEnvoyees >= count($seuils) || $jours < $seuils[$dejaEnvoyees]) {
            return null;
        }

        return [
            'user_id' => $destinataire,
            'type' => $type,
            'title' => $title,
            'message' => sprintf($message, $jours),
            'url' => $vente,
        ];
    }

    /**
     * Relance « Vous avez acheté cet article ? » pour une vente en main propre
     * déclarée par le vendeur, encore sans réponse de l'acheteur.
     *
     * @return array{user_id:int,type:string,title:string,message:string,url:string}|null
     */
    public static function relanceConfirmationMainPropre(Transaction $t, ?Carbon $maintenant = null): ?array
    {
        $maintenant ??= now();
        if (! $t->attendReponseAcheteur()) {
            return null;
        }

        $type = 'cash_sale_confirm_reminder';
        $vente = route('account.transactions.show', $t, absolute: false);
        $deja = Notification::where('user_id', $t->buyer_id)->where('type', $type)->where('url', $vente)->count();
        $seuils = self::RELANCES[$type];
        $jours = (int) Carbon::parse($t->created_at)->diffInDays($maintenant);

        if ($deja >= count($seuils) || $jours < $seuils[$deja]) {
            return null;
        }

        $titre = $t->listing?->title ?? 'cet article';
        $vendeur = $t->seller?->name ?? 'Le vendeur';

        return [
            'user_id' => $t->buyer_id,
            'type' => $type,
            'title' => '👉 Confirmez votre achat',
            'message' => "{$vendeur} indique vous avoir vendu « {$titre} » en main propre. Un clic pour confirmer : ça compte pour son classement, et vous pourrez lui laisser un avis.",
            'url' => $vente,
        ];
    }

    /** Passe en revue les ventes en cours et envoie les relances dues. */
    public static function envoyerRelances(?Carbon $maintenant = null): int
    {
        $envoyees = 0;

        Transaction::query()
            ->where('payment_method', 'especes')
            ->whereNotNull('buyer_id')
            ->whereNull('acheteur_confirme_at')
            ->whereNull('acheteur_conteste_at')
            ->where('created_at', '>=', now()->subDays(30))
            ->with(['listing', 'seller'])
            ->chunkById(200, function ($ventes) use (&$envoyees, $maintenant) {
                foreach ($ventes as $t) {
                    if ($relance = self::relanceConfirmationMainPropre($t, $maintenant)) {
                        Notification::create($relance);
                        $envoyees++;
                    }
                }
            });

        Transaction::query()
            ->where('status', 'paid')
            ->enLigne()
            ->where('created_at', '>=', now()->subDays(30))
            ->with(['listing', 'buyer', 'seller', 'relayPoint'])
            ->chunkById(200, function ($ventes) use (&$envoyees, $maintenant) {
                foreach ($ventes as $t) {
                    if ($relance = self::relanceDue($t, $maintenant)) {
                        Notification::create($relance);
                        $envoyees++;
                    }
                }
            });

        return $envoyees;
    }

    // --- Favoris ----------------------------------------------------------------

    /** Prix d'un favori en baisse : on prévient ceux qui l'ont aimé. */
    public static function baisseDePrix(Listing $annonce, float $ancienPrix): int
    {
        $nouveau = (float) $annonce->price;

        if ($annonce->status !== 'published' || $nouveau <= 0 || $nouveau >= $ancienPrix) {
            return 0;
        }

        $url = route('listings.show', $annonce, absolute: false);
        $envoyees = 0;

        $annonce->favoritedBy()
            ->where('users.id', '!=', $annonce->user_id)
            ->where(fn ($q) => $q->whereNull('is_banned')->orWhere('is_banned', false))
            ->get(['users.id'])
            ->each(function ($membre) use ($annonce, $ancienPrix, $nouveau, $url, &$envoyees) {
                // Un vendeur qui baisse plusieurs fois dans la journée : un seul message.
                $recent = Notification::where('user_id', $membre->id)->where('type', 'favorite_price_drop')
                    ->where('url', $url)->where('created_at', '>=', now()->subDay())->exists();

                if ($recent) {
                    return;
                }

                Notification::create([
                    'user_id' => $membre->id,
                    'type' => 'favorite_price_drop',
                    'title' => '📉 Baisse de prix sur un favori',
                    'message' => '« ' . $annonce->title . ' » passe de ' . TableauDeBord::euros($ancienPrix) . ' à ' . TableauDeBord::euros($nouveau) . '.',
                    'url' => $url,
                ]);
                $envoyees++;
            });

        return $envoyees;
    }
}
