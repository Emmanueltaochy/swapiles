<?php

namespace App\Filament\Pages;

use App\Models\Listing;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

/**
 * Points 5 & 9 : rapport d'audit de la migration (avant toute correction).
 * Explique notamment pourquoi certaines annonces ne sont pas passées en CB, et
 * mesure les champs revenus à leur valeur par défaut (adresses, e-mail vérifié).
 */
class MigrationAudit extends Page
{
    protected static ?string $navigationLabel = 'Audit migration';

    protected static ?string $title = 'Audit migration';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.migration-audit';

    public function getViewData(): array
    {
        $operational = fn (Builder $q) => $q->whereNotNull('stripe_account_id')
            ->where('stripe_charges_enabled', true)
            ->where('stripe_payouts_enabled', true);

        $chargesOnly = fn (Builder $q) => $q->whereNotNull('stripe_account_id')
            ->where('stripe_charges_enabled', true)
            ->where('stripe_payouts_enabled', false);

        $totalUsers = User::count();
        $withAddress = User::where(fn ($q) => $q->whereNotNull('address_line1')->orWhereNotNull('postal_code'))->count();
        $verified = User::whereNotNull('email_verified_at')->count();
        $sellersOperational = User::where($operational)->count();
        $sellersChargesOnly = User::where($chargesOnly)->count();

        $published = Listing::where('status', 'published');

        // Pourquoi une annonce publiée n'est-elle pas en CB ? (point 5)
        $cbOn = (clone $published)->where('requires_online_payment', true)->count();

        $cbOffEligibleButOperational = (clone $published)
            ->where('requires_online_payment', false)
            ->whereIn('listing_type', ['achat', 'negoce-prix'])
            ->where('price', '>', 0)
            ->whereHas('user', $operational)
            ->count(); // devrait être 0 : la commande enable-cb les couvre

        $cbOffPayoutsMissing = (clone $published)
            ->where('requires_online_payment', false)
            ->whereHas('user', $chargesOnly)
            ->count(); // vendeurs identité OK mais IBAN manquant (point 10)

        $cbOffIneligible = (clone $published)
            ->where('requires_online_payment', false)
            ->where(fn ($q) => $q->whereNotIn('listing_type', ['achat', 'negoce-prix'])->orWhere('price', '<=', 0))
            ->count();

        $noPhoto = (clone $published)->whereDoesntHave('images')->count();

        return [
            'config' => [
                'APP_URL' => config('app.url'),
                'MAIL_MAILER' => config('mail.default'),
                'MAIL_HOST' => config('mail.mailers.' . config('mail.default') . '.host') ?? '—',
                'MAIL_FROM' => config('mail.from.address'),
            ],
            'rows' => [
                ['label' => 'Membres', 'value' => $totalUsers, 'note' => ''],
                ['label' => 'Avec adresse', 'value' => $withAddress, 'note' => $totalUsers ? round($withAddress / $totalUsers * 100) . ' %' : '—'],
                ['label' => 'E-mail vérifié', 'value' => $verified, 'note' => 'non bloquant (pas de middleware verified)'],
                ['label' => 'Vendeurs opérationnels (CB + IBAN)', 'value' => $sellersOperational, 'note' => ''],
                ['label' => 'Vendeurs identité OK mais IBAN manquant', 'value' => $sellersChargesOnly, 'note' => 'point 10 : relance IBAN'],
            ],
            'listingRows' => [
                ['label' => 'Annonces publiées en CB', 'value' => $cbOn, 'tone' => 'ok'],
                ['label' => 'CB à activer alors que vendeur opérationnel', 'value' => $cbOffEligibleButOperational, 'tone' => $cbOffEligibleButOperational > 0 ? 'warn' : 'ok'],
                ['label' => 'CB off car vendeur sans IBAN (normal)', 'value' => $cbOffPayoutsMissing, 'tone' => 'info'],
                ['label' => 'CB off car inéligible (type / prix 0)', 'value' => $cbOffIneligible, 'tone' => 'info'],
                ['label' => 'Publiées sans photo', 'value' => $noPhoto, 'tone' => $noPhoto > 0 ? 'warn' : 'ok'],
            ],
        ];
    }
}
