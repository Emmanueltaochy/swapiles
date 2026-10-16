@extends('layouts.app')

@section('title', 'Mon argent — Swap\'Îles')

@section('content')
@php
    $stripeReady = $stripeReady ?? (
        $user->stripe_account_id
        && $user->stripe_charges_enabled
        && $user->stripe_payouts_enabled
        && $user->stripe_details_submitted
    );
@endphp
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        <a href="{{ route('account.dashboard') }}" class="mb-3 inline-block text-sm font-semibold text-teal-700 hover:text-teal-900">← Mon compte</a>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900">Mon argent</h1>
                <p class="text-gray-500 mt-2">Suivez vos ventes, vos virements et votre argent disponible.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-8">
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-sm text-gray-500">En attente</p>
                <p class="text-3xl font-extrabold text-gray-900 mt-2">{{ number_format($pendingAmount, 2, ',', ' ') }} €</p>
                <p class="text-xs text-gray-400 mt-1">Paiement reçu, réception non confirmée.</p>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-sm text-gray-500">Virement en cours</p>
                <p class="text-3xl font-extrabold text-yellow-700 mt-2">{{ number_format($processingAmount, 2, ',', ' ') }} €</p>
                <p class="text-xs text-gray-400 mt-1">Délai estimé : 1 à 3 jours ouvrés.</p>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-sm text-gray-500">Déjà versé</p>
                <p class="text-3xl font-extrabold text-green-700 mt-2">{{ number_format($paidAmount, 2, ',', ' ') }} €</p>
                <p class="text-xs text-gray-400 mt-1">Argent envoyé sur votre compte bancaire.</p>
            </div>
        </div>

        {{-- Mention explicite : le solde ne concerne que les ventes CB (carte). --}}
        <p class="mt-3 text-sm text-gray-500">
            💳 Ce solde ne concerne que les <span class="font-semibold text-gray-700">ventes par carte (CB)</span>.
            Les ventes en <span class="font-semibold">espèces</span>, <span class="font-semibold">dons</span> et <span class="font-semibold">échanges</span>
            apparaissent dans « Toutes mes ventes » ci-dessous, mais jamais dans le solde.
        </p>

        <div class="mt-4 flex items-start gap-3 rounded-2xl border border-teal-100 bg-teal-50 p-4">
            <span class="text-xl" aria-hidden="true">🛡️</span>
            <p class="text-sm text-teal-900">
                <span class="font-bold">Comment ça marche&nbsp;?</span>
                Dès qu'un acheteur confirme la réception, votre argent passe en <span class="font-semibold">« virement en cours »</span>, puis arrive sur votre compte bancaire sous 1 à 3 jours ouvrés.
            </p>
        </div>

        @if(!$stripeReady)
            @php $moneyWaiting = ($pendingAmount + ($processingAmount ?? 0)); @endphp
            <div class="bg-yellow-50 border border-yellow-200 rounded-3xl p-5 mt-8">
                @if($moneyWaiting > 0)
                    <h2 class="text-xl font-extrabold text-yellow-900">💶 {{ number_format($moneyWaiting, 2, ',', ' ') }} € t’attendent</h2>
                    <p class="text-sm text-yellow-800 mt-1">
                        Ta vente est payée et sécurisée. Ajoute ton IBAN pour recevoir ton argent —
                        <span class="font-semibold">2 minutes, pas de pièce d’identité à ce stade</span>.
                    </p>
                @else
                    <h2 class="text-xl font-extrabold text-yellow-900">Recevoir mes paiements</h2>
                    <p class="text-sm text-yellow-800 mt-1">
                        Ajoute ton IBAN pour être payé automatiquement dès qu’un acheteur règle par carte —
                        <span class="font-semibold">2 minutes, pas de pièce d’identité à ce stade</span>.
                    </p>
                @endif

                <a href="{{ route('stripe.connect.activate') }}" class="inline-flex mt-4 bg-yellow-500 hover:bg-yellow-600 text-white font-extrabold px-6 py-3 rounded-2xl transition">
                    {{ $moneyWaiting > 0 ? 'Ajouter mon IBAN et être payé' : 'Activer mon portefeuille' }}
                </a>
            </div>
        @else
            <div class="bg-green-50 border border-green-200 rounded-3xl p-5 mt-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-green-100 text-2xl" aria-hidden="true">✅</span>
                    <div>
                        <h2 class="text-lg font-extrabold text-green-900">Portefeuille activé</h2>
                        <p class="text-sm text-green-800 mt-0.5">
                            @if($bankInfo && !empty($bankInfo['last4']))
                                Compte bancaire lié{{ $bankInfo['bank'] ? ' (' . $bankInfo['bank'] . ')' : '' }} : IBAN se terminant par <span class="font-bold">•••• {{ $bankInfo['last4'] }}</span>. Vos ventes sont versées automatiquement.
                            @else
                                Votre IBAN est enregistré. Vos ventes sont versées automatiquement sur votre compte bancaire.
                            @endif
                        </p>
                    </div>
                </div>
                <a href="{{ route('stripe.connect.activate') }}" class="shrink-0 inline-flex items-center justify-center rounded-2xl border border-green-300 bg-white px-4 py-2.5 text-sm font-semibold text-green-800 hover:bg-green-50 transition">
                    Gérer / modifier
                </a>
            </div>
        @endif

        {{-- Récapitulatif du mois : l'écart ventes/sécurisé est volontairement visible. --}}
        <div class="mt-8 rounded-3xl border border-gray-100 bg-white shadow-sm p-5">
            <p class="text-sm text-gray-500">Ce mois-ci</p>
            <p class="mt-1 text-lg text-gray-900">
                <span class="font-extrabold">{{ number_format($monthlySales, 0, ',', ' ') }} € de ventes</span>,
                dont <span class="font-extrabold text-teal-700">{{ number_format($monthlySecured, 0, ',', ' ') }} € sécurisés</span>
            </p>
            @if($monthlySales > $monthlySecured)
                <p class="mt-1 text-sm text-gray-500">
                    L'écart correspond aux ventes en espèces : encaisse en paiement sécurisé pour être payé avant la remise, sans avance ni impayé.
                </p>
            @endif
        </div>

        {{-- Journal : TOUTES les ventes, tous modes. Informatif, ce n'est pas un solde. --}}
        <div class="mt-6">@include('partials.incitation-lot', ['vendeur' => $user])</div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden mt-6" data-wallet-journal>
            <div class="p-5 border-b border-gray-100">
                <h2 class="text-xl font-extrabold text-gray-900">Toutes mes ventes</h2>
                <p class="text-sm text-gray-500 mt-1">Tous vos échanges, quel que soit le mode. Filtrez ci-dessous.</p>

                <div class="mt-4 flex flex-wrap gap-2" data-wallet-filters>
                    @foreach([
                        'all' => 'Tout',
                        'online' => 'CB',
                        'cash' => 'Espèces',
                        'gift' => 'Don',
                        'exchange' => 'Échange',
                    ] as $key => $label)
                        <button type="button" data-filter="{{ $key }}"
                            class="wallet-filter-btn text-xs font-bold px-3 py-1.5 rounded-full transition {{ $key === 'all' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse($sales as $sale)
                    @php($mode = $sale->payment_mode)
                    <div class="p-4 flex gap-4 wallet-sale-row" data-mode="{{ $mode }}">
                        <div class="w-16 h-16 rounded-2xl bg-gray-100 overflow-hidden shrink-0">
                            @if($sale->listing && $sale->listing->images->first())
                                <img src="{{ $sale->listing->images->first()->url }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-300 text-2xl">📦</div>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            @php($badge = [
                                'online' => 'bg-teal-100 text-teal-800',
                                'cash' => 'bg-amber-100 text-amber-800',
                                'gift' => 'bg-pink-100 text-pink-800',
                                'exchange' => 'bg-indigo-100 text-indigo-800',
                            ][$mode] ?? 'bg-gray-100 text-gray-700')
                            <p class="font-bold text-gray-900 truncate">{{ $sale->listing->title ?? 'Annonce supprimée' }}</p>
                            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-gray-500 min-w-0">
                                <span class="shrink-0 text-[11px] font-bold px-2 py-0.5 rounded-full {{ $badge }}">{{ $sale->payment_mode_label }}</span>
                                <span class="truncate">{{ $sale->buyer->name ?? 'Utilisateur' }} · {{ $sale->created_at?->format('d/m') }}</span>
                            </p>
                            <div class="mt-1.5 flex items-center justify-between gap-2">
                                <p class="text-sm font-extrabold text-gray-900 whitespace-nowrap">
                                    {{ number_format(\App\Support\SellerWallet::net($sale), 2, ',', ' ') }} €
                                    @if($sale->is_secured)
                                        <span class="text-xs font-semibold text-teal-700">net</span>
                                    @else
                                        <span class="text-xs font-medium text-gray-400">hors appli</span>
                                    @endif
                                </p>
                                @if($sale->wallet_status === 'paid')
                                    <span class="shrink-0 whitespace-nowrap text-xs font-bold px-2.5 py-1 rounded-full bg-green-100 text-green-800">Virement envoyé</span>
                                @elseif($sale->wallet_status === 'processing')
                                    <span class="shrink-0 whitespace-nowrap text-xs font-bold px-2.5 py-1 rounded-full bg-yellow-100 text-yellow-800">Virement en cours</span>
                                @elseif($sale->is_secured && $sale->status === 'paid')
                                    <span class="shrink-0 whitespace-nowrap text-xs font-bold px-2.5 py-1 rounded-full bg-gray-100 text-gray-700">Attente réception</span>
                                @else
                                    <span class="shrink-0 whitespace-nowrap text-xs font-bold px-2.5 py-1 rounded-full bg-gray-100 text-gray-700">{{ ['paid'=>'Payée','pending'=>'En attente','completed'=>'Terminée','cancelled'=>'Annulée','refunded'=>'Remboursée'][$sale->status] ?? $sale->status }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-10 text-center text-gray-500">
                        Aucune vente pour le moment.
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</section>

<script>
    (function () {
        var journal = document.querySelector('[data-wallet-journal]');
        if (!journal) return;

        var buttons = journal.querySelectorAll('.wallet-filter-btn');
        var rows = journal.querySelectorAll('.wallet-sale-row');

        function apply(mode) {
            rows.forEach(function (row) {
                var show = (mode === 'all') || (row.getAttribute('data-mode') === mode);
                row.style.display = show ? '' : 'none';
            });
            buttons.forEach(function (btn) {
                var active = btn.getAttribute('data-filter') === mode;
                btn.classList.toggle('bg-gray-900', active);
                btn.classList.toggle('text-white', active);
                btn.classList.toggle('bg-gray-100', !active);
                btn.classList.toggle('text-gray-700', !active);
            });
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                apply(btn.getAttribute('data-filter'));
            });
        });
    })();
</script>
@endsection
