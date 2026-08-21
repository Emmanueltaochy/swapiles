<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendTransactionStatusEmails implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;

    /**
     * @param  string  $event  paid | shipped | received | released | relay_deposited
     */
    public function __construct(
        public int $transactionId,
        public string $event
    ) {
    }

    public function handle(): void
    {
        $t = Transaction::with(['buyer', 'seller', 'listing', 'relayPoint.manager'])->find($this->transactionId);

        if (!$t) {
            return;
        }

        $net = number_format($this->netSellerAmount($t), 2, ',', ' ');

        try {
            $url = route('account.transactions.show', $t);
        } catch (\Throwable $e) {
            $url = 'https://swapiles.com';
        }

        $messages = self::messagesForTransaction($t, $this->event);

        // Point 19 — à la vente, si le vendeur n'a pas encore de compte
        // opérationnel, on greffe la sollicitation KYC (« ton argent t'attend,
        // ajoute ton IBAN ») sur l'e-mail vendeur existant. Piloté par un flag
        // activé par défaut (features.sale_kyc_email).
        if (
            $this->event === 'paid'
            && !empty($messages['seller'])
            && $t->seller
            && !$t->seller->stripe_payouts_enabled
            && config('features.sale_kyc_email')
        ) {
            try {
                $walletUrl = route('account.wallet.index');
            } catch (\Throwable $e) {
                $walletUrl = 'https://swapiles.com';
            }

            $messages['seller'][1] .= "\n\n💶 {$net} € t'attendent. Pour les recevoir, ajoute ton IBAN — "
                . "2 minutes, sans pièce d'identité à ce stade : {$walletUrl}";
        }

        if (!empty($messages['buyer']) && $t->buyer?->email) {
            $this->send($t->buyer->email, $messages['buyer'][0], $messages['buyer'][1] . $this->footer($url));
        }

        if (!empty($messages['seller']) && $t->seller?->email) {
            $this->send($t->seller->email, $messages['seller'][0], $messages['seller'][1] . $this->footer($url));
        }

        // Point relais : e-mail au commerçant gérant (sans lien transaction,
        // il gère depuis son espace relais).
        if (!empty($messages['merchant']) && $t->relayPoint?->manager?->email) {
            $relayUrl = $this->relayDashboardUrl();
            $this->send($t->relayPoint->manager->email, $messages['merchant'][0], $messages['merchant'][1] . "\n\nMon espace relais : {$relayUrl}\n\nL'équipe Swap'Îles");
        }
    }

    /** Montant net vendeur (prix affiché ; commission 0 %). */
    private function netSellerAmount(Transaction $t): float
    {
        return (float) $t->seller_amount > 0
            ? (float) $t->seller_amount
            : max(0, (float) $t->amount - (float) $t->commission - (float) $t->buyer_protection_fee - (float) $t->shipping_fee);
    }

    /**
     * Construit les messages (sujet + corps) par destinataire pour un événement.
     * Public et statique pour être testable sans envoi réel.
     *
     * @return array{buyer?: array{0:string,1:string}, seller?: array{0:string,1:string}, merchant?: array{0:string,1:string}}
     */
    public static function messagesForTransaction(Transaction $t, string $event): array
    {
        $title = $t->listing?->title ?? 'votre article';
        $amount = number_format((float) $t->amount, 2, ',', ' ');
        $netRaw = (float) $t->seller_amount > 0
            ? (float) $t->seller_amount
            : max(0, (float) $t->amount - (float) $t->commission - (float) $t->buyer_protection_fee - (float) $t->shipping_fee);
        $net = number_format($netRaw, 2, ',', ' ');

        if ($t->delivery_method === 'relay') {
            return self::relayMessages($t, $event, $title, $amount, $net);
        }

        return self::standardMessages($event, $title, $amount, $net);
    }

    private static function standardMessages(string $event, string $title, string $amount, string $net): array
    {
        return match ($event) {
            'paid' => [
                'buyer' => [
                    "✅ Paiement confirmé — {$title}",
                    "Bonjour,\n\nVotre paiement de {$amount} € pour « {$title} » est bien confirmé. Le vendeur va préparer votre colis.",
                ],
                'seller' => [
                    "🎉 Vous avez vendu {$title} !",
                    "Bonjour,\n\nBonne nouvelle : « {$title} » vient d'être payé ({$amount} €). Préparez le colis puis générez votre bordereau Colissimo, ou convenez de la remise en main propre.",
                ],
            ],
            'shipped' => [
                'buyer' => [
                    "📦 Votre commande est expédiée — {$title}",
                    "Bonjour,\n\nLe vendeur vient d'expédier « {$title} ». Dès réception, pensez à confirmer la réception depuis vos transactions pour finaliser l'achat.",
                ],
                'seller' => [
                    "📦 Expédition enregistrée — {$title}",
                    "Bonjour,\n\nVous avez marqué « {$title} » comme expédié. L'acheteur vient d'en être informé.",
                ],
            ],
            'received' => [
                'buyer' => [
                    "✅ Réception confirmée — {$title}",
                    "Bonjour,\n\nVous avez confirmé la réception de « {$title} ». Merci de votre confiance et à bientôt sur Swap'Îles !",
                ],
                'seller' => [
                    "💶 Réception confirmée — {$title}",
                    "Bonjour,\n\nL'acheteur a confirmé la réception de « {$title} ». Votre paiement va être versé sur votre compte bancaire.",
                ],
            ],
            'released' => [
                'seller' => [
                    "💶 Paiement envoyé — {$title}",
                    "Bonjour,\n\nVotre paiement de {$net} € pour la vente de « {$title} » a été envoyé vers votre compte bancaire (délai habituel : 1 à 3 jours ouvrés).",
                ],
            ],
            default => [],
        };
    }

    /** Messages spécifiques au point relais (retrait chez un commerçant). */
    private static function relayMessages(Transaction $t, string $event, string $title, string $amount, string $net): array
    {
        $name = $t->relayPoint?->name ?? 'ton point relais';
        $addr = $t->relayPoint?->fullAddress();
        $where = $addr ? "{$name} ({$addr})" : $name;
        $hours = $t->relayPoint?->opening_hours ? "\nHoraires : {$t->relayPoint->opening_hours}" : '';
        $code = (string) $t->relay_pickup_code;
        $buyerName = $t->buyer?->name ?? 'un acheteur';
        $sellerName = $t->seller?->name ?? 'un vendeur';

        return match ($event) {
            'paid' => [
                'buyer' => [
                    "✅ Paiement confirmé — {$title}",
                    "Bonjour,\n\nTon paiement de {$amount} € pour « {$title} » est confirmé et sécurisé. Dès que le vendeur dépose ton colis au point relais {$where}, tu recevras ton code de retrait par e-mail.",
                ],
                'seller' => [
                    "🎉 Tu as vendu {$title} !",
                    "Bonjour,\n\n« {$title} » vient d'être payé ({$amount} €). Dépose le colis au point relais {$where}.{$hours}\n\nL'acheteur viendra le retirer avec son code. Ton paiement de {$net} € te sera versé une fois le retrait confirmé.",
                ],
                'merchant' => [
                    "📦 Un colis Swap'Îles va arriver — {$title}",
                    "Bonjour,\n\nUn colis va être déposé chez toi par {$sellerName} (pour {$buyerName}). Quand il arrive, confirme sa réception dans ton espace relais ; tu remettras ensuite le colis à l'acheteur contre son code de retrait.",
                ],
            ],
            'relay_deposited' => [
                'buyer' => [
                    "🏪 Ton colis t'attend au point relais — {$title}",
                    "Bonjour,\n\nBonne nouvelle : ton colis « {$title} » est disponible au point relais {$where}.{$hours}\n\n🔑 Ton code de retrait : {$code}\n\nPrésente ce code au commerçant pour récupérer ton colis. Pense ensuite à confirmer le retrait depuis tes transactions.",
                ],
                'seller' => [
                    "📦 Dépôt enregistré — {$title}",
                    "Bonjour,\n\nTon colis « {$title} » est bien déposé au point relais. L'acheteur vient d'être prévenu qu'il peut venir le retirer.",
                ],
            ],
            'received' => [
                'buyer' => [
                    "✅ Colis retiré — {$title}",
                    "Bonjour,\n\nTu as bien retiré « {$title} » au point relais. Merci de ta confiance et à bientôt sur Swap'Îles !",
                ],
                'seller' => [
                    "💶 Colis retiré — {$title}",
                    "Bonjour,\n\nL'acheteur a retiré « {$title} » au point relais. Ton paiement va être versé sur ton compte bancaire.",
                ],
            ],
            'released' => [
                'seller' => [
                    "💶 Paiement envoyé — {$title}",
                    "Bonjour,\n\nTon paiement de {$net} € pour la vente de « {$title} » a été envoyé vers ton compte bancaire (délai habituel : 1 à 3 jours ouvrés).",
                ],
            ],
            default => [],
        };
    }

    private function relayDashboardUrl(): string
    {
        try {
            return route('account.relay.dashboard');
        } catch (\Throwable $e) {
            return 'https://swapiles.com';
        }
    }

    private function footer(string $url): string
    {
        return "\n\nVoir la transaction : {$url}\n\nL'équipe Swap'Îles\nhttps://swapiles.com";
    }

    private function send(string $to, string $subject, string $body): void
    {
        Mail::raw($body, function ($mail) use ($to, $subject) {
            $mail->from('contact@swapiles.com', "Swap'Îles")
                ->to($to)
                ->subject($subject);
        });
    }
}
