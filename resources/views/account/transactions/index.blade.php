@extends('layouts.app')

@section('title', 'Mes transactions — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-6xl mx-auto px-4">

        @php
            $allTransactions = $purchases->concat($sales);

            $toShipCount = $sales
                ->where('status', 'paid')
                ->where('shipping_status', 'pending')
                ->count();

            $inTransitCount = $allTransactions
                ->where('shipping_status', 'shipped')
                ->count();

            $toConfirmCount = $purchases
                ->where('status', 'paid')
                ->where('shipping_status', 'shipped')
                ->count();

            $completedCount = $allTransactions
                ->where('status', 'completed')
                ->count();
        @endphp

        @if(session('status'))
            <div class="mb-6 rounded-2xl bg-teal-50 border border-teal-100 text-teal-800 p-4 text-sm font-semibold">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900">Mes transactions</h1>
                <p class="text-gray-500 mt-2">Suivez vos achats, ventes, expéditions et paiements en un coup d'œil.</p>
            </div>

            <a href="{{ route('account.dashboard') }}" class="inline-flex items-center gap-2 bg-white border border-gray-100 text-gray-800 font-bold px-5 py-3 rounded-2xl shadow-sm hover:bg-gray-50 transition">
                ← Retour au compte
            </a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-8">
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-2xl" aria-hidden="true">📦</p>
                <p class="text-3xl font-extrabold text-orange-600 mt-1">{{ $toShipCount }}</p>
                <p class="text-sm text-gray-500 mt-1">À expédier</p>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-2xl" aria-hidden="true">🚚</p>
                <p class="text-3xl font-extrabold text-blue-600 mt-1">{{ $inTransitCount }}</p>
                <p class="text-sm text-gray-500 mt-1">En livraison</p>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-2xl" aria-hidden="true">✅</p>
                <p class="text-3xl font-extrabold text-purple-600 mt-1">{{ $toConfirmCount }}</p>
                <p class="text-sm text-gray-500 mt-1">À confirmer</p>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-2xl" aria-hidden="true">🎉</p>
                <p class="text-3xl font-extrabold text-green-600 mt-1">{{ $completedCount }}</p>
                <p class="text-sm text-gray-500 mt-1">Terminées</p>
            </div>
        </div>

        <div class="mt-6 flex items-start gap-3 rounded-2xl border border-teal-100 bg-teal-50 p-4">
            <span class="text-xl" aria-hidden="true">🛡️</span>
            <p class="text-sm text-teal-900">
                <span class="font-bold">Paiement protégé.</span>
                Pour les achats par CB, l'argent n'est versé au vendeur qu'une fois la réception confirmée. Chaque étape est suivie ci-dessous&nbsp;: <span class="font-semibold">Paiement → Expédié → Reçu → Virement</span>.
            </p>
        </div>

        {{-- Bascule Achats / Ventes — mobile & tablette uniquement --}}
        <div class="lg:hidden sticky top-16 z-30 mt-6">
            <div class="grid grid-cols-2 gap-1 rounded-2xl bg-gray-100 p-1 shadow-md ring-1 ring-gray-200" role="tablist" aria-label="Basculer entre ventes et achats">
                <button type="button" data-tx-tab="ventes" role="tab"
                        class="tx-tab rounded-xl px-4 py-2.5 text-sm font-extrabold transition">
                    💰 Ventes <span class="text-xs font-bold opacity-60">({{ $sales->count() }})</span>
                </button>
                <button type="button" data-tx-tab="achats" role="tab"
                        class="tx-tab rounded-xl px-4 py-2.5 text-sm font-extrabold transition">
                    🛍️ Achats <span class="text-xs font-bold opacity-60">({{ $purchases->count() }})</span>
                </button>
            </div>
        </div>

        <div id="tx-panels" class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-3 lg:mt-8">
            <div data-tx-panel="ventes" class="lg:block bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <h2 class="text-xl font-extrabold mb-4 hidden lg:flex items-center gap-2">💰 Mes ventes
                    <span class="text-xs font-bold text-gray-400">({{ $sales->count() }})</span>
                </h2>

                @forelse($sales as $transaction)
                    @include('account.transactions.partials.card', ['transaction' => $transaction, 'type' => 'sale'])
                @empty
                    <div class="py-10 text-center">
                        <div class="text-4xl" aria-hidden="true">💰</div>
                        <p class="mt-3 font-semibold text-gray-900">Aucune vente pour le moment</p>
                        <a href="{{ route('account.listings.create') }}" class="mt-4 inline-flex rounded-2xl bg-teal-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-teal-700 transition">Déposer une annonce</a>
                    </div>
                @endforelse
            </div>

            <div data-tx-panel="achats" class="hidden lg:block bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <h2 class="text-xl font-extrabold mb-4 hidden lg:flex items-center gap-2">🛍️ Mes achats
                    <span class="text-xs font-bold text-gray-400">({{ $purchases->count() }})</span>
                </h2>

                @forelse($purchases as $transaction)
                    @include('account.transactions.partials.card', ['transaction' => $transaction, 'type' => 'purchase'])
                @empty
                    <div class="py-10 text-center">
                        <div class="text-4xl" aria-hidden="true">🛍️</div>
                        <p class="mt-3 font-semibold text-gray-900">Aucun achat pour le moment</p>
                        <a href="{{ route('search') }}" class="mt-4 inline-flex rounded-2xl bg-teal-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-teal-700 transition">Explorer les annonces</a>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</section>

<script>
(function () {
    const tabs = document.querySelectorAll('[data-tx-tab]');
    const panels = document.querySelectorAll('[data-tx-panel]');
    if (!tabs.length || !panels.length) return;

    function setTab(name) {
        tabs.forEach(function (t) {
            const on = t.dataset.txTab === name;
            t.classList.toggle('bg-white', on);
            t.classList.toggle('text-gray-900', on);
            t.classList.toggle('shadow-sm', on);
            t.classList.toggle('text-gray-500', !on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function (p) {
            // N'affecte que le mobile : la classe lg:block garde les 2 colonnes sur ordinateur.
            p.classList.toggle('hidden', p.dataset.txPanel !== name);
        });
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function () { setTab(t.dataset.txTab); });
    });

    setTab('ventes');

    // Bonus : balayage horizontal sur mobile pour changer d'onglet.
    const wrap = document.getElementById('tx-panels');
    let x0 = null, y0 = null;
    wrap.addEventListener('touchstart', function (e) {
        const t = e.changedTouches[0];
        x0 = t.clientX; y0 = t.clientY;
    }, { passive: true });
    wrap.addEventListener('touchend', function (e) {
        if (x0 === null) return;
        const t = e.changedTouches[0];
        const dx = t.clientX - x0, dy = t.clientY - y0;
        if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) {
            setTab(dx < 0 ? 'achats' : 'ventes');
        }
        x0 = y0 = null;
    }, { passive: true });
})();
</script>
@endsection
