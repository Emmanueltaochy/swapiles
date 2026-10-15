@extends('layouts.app')

@section('title', 'Inviter des amis — Swap’Îles')

@section('content')
@php
    $message = 'Je vends et j’achète sur Swap’Îles 🌴 Inscris-toi avec mon lien, tes frais de protection sont offerts sur ton 1er achat ! ' . $lien;
    $euros = fn (int $c) => number_format($c / 100, 2, ',', ' ') . ' €';
    $badges = [
        'inscrit' => ['Inscrit', 'bg-gray-100 text-gray-700'],
        'achat_en_cours' => ['1er achat en attente', 'bg-amber-100 text-amber-800'],
        'valide' => ['Validé ✅', 'bg-emerald-100 text-emerald-800'],
        'refuse' => ['Non éligible', 'bg-gray-100 text-gray-500'],
        'annule' => ['Annulé', 'bg-gray-100 text-gray-500'],
    ];
@endphp
<section class="mx-auto max-w-2xl px-4 py-6 sm:py-10" data-parrainage data-partage-url="{{ route('parrainage.partage', absolute: false) }}">
    <h1 class="text-2xl font-extrabold text-gray-900 sm:text-3xl">💚 {{ $recompense > 0 ? 'Gagne ' . \App\Support\ParrainageService::montantCourt($recompense) . ' par ami invité' : 'Invite tes amis' }}</h1>
    @unless(config('referral.enabled'))
        <p class="mt-2 inline-block rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800">🧪 Mode test : visible seulement par vous</p>
    @endunless

    @if(session('status'))
        <p class="mt-4 rounded-xl bg-teal-50 p-3 text-sm font-semibold text-teal-800" role="status">{{ session('status') }}</p>
    @endif

    <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
            <p class="text-2xl">🎁</p>
            <p class="mt-1 font-bold text-emerald-900">Ton ami</p>
            <p class="text-emerald-900">Protection acheteur offerte sur son 1er achat.</p>
        </div>
        <div class="rounded-2xl border border-teal-100 bg-teal-50 p-4">
            <p class="text-2xl">💚</p>
            <p class="mt-1 font-bold text-teal-900">Toi</p>
            @if($recompense > 0)
                <p class="text-teal-900"><strong>{{ number_format($recompense, 2, ',', ' ') }} €</strong> de crédit quand son 1er achat est terminé.</p>
            @else
                <p class="text-teal-900">Tu fais grandir Swap’Îles, et à {{ $palier }} amis tu deviens <strong>Ambassadeur</strong>.</p>
            @endif
        </div>
    </div>

    {{-- Code et partage --}}
    <div class="mt-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-gray-500">Ton code</p>
        <div class="mt-1 flex items-center gap-3">
            <p class="font-mono text-3xl font-extrabold tracking-wider text-gray-900">{{ $moi->code_parrainage }}</p>
            <button type="button" data-copier="{{ $lien }}" class="ml-auto rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-bold text-gray-800 hover:bg-gray-200">📋 Copier le lien</button>
        </div>
        <p class="mt-1 break-all text-xs text-gray-500">{{ $lien }}</p>

        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
            <a href="https://wa.me/?text={{ rawurlencode($message) }}" data-partager="whatsapp" target="_blank" rel="noopener"
               class="col-span-2 flex items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-3 font-bold text-white sm:col-span-1">WhatsApp</a>
            <button type="button" data-partager="instagram" class="rounded-xl bg-gradient-to-r from-fuchsia-600 to-orange-500 px-4 py-3 font-bold text-white">Instagram</button>
            <a href="sms:?&body={{ rawurlencode($message) }}" data-partager="sms" class="flex items-center justify-center rounded-xl bg-sky-600 px-4 py-3 font-bold text-white">SMS</a>
            <button type="button" data-partager="autre" class="col-span-2 rounded-xl border-2 border-gray-200 px-4 py-3 font-bold text-gray-800 sm:col-span-1">Autres…</button>
        </div>

        @unless($moi->code_personnalise_at)
            <details class="mt-4 text-sm" @if($errors->has('nouveau_code')) open @endif>
                <summary class="cursor-pointer font-semibold text-teal-700">✏️ Personnaliser mon code (une seule fois)</summary>
                <form method="POST" action="{{ route('parrainage.personnaliser') }}" class="mt-3 flex gap-2">@csrf
                    <label for="nouveau_code" class="sr-only">Nouveau code</label>
                    <input id="nouveau_code" name="nouveau_code" value="{{ old('nouveau_code') }}" maxlength="12" placeholder="Ex : LEASTPIERRE" autocapitalize="characters"
                           class="min-w-0 flex-1 rounded-xl border border-gray-200 px-3 py-2.5 uppercase focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                    <button class="rounded-xl bg-teal-600 px-4 font-bold text-white">Valider</button>
                </form>
                @error('nouveau_code')<p class="mt-1 text-red-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-500">4 à 12 lettres ou chiffres. Ton ancien lien continuera de marcher.</p>
            </details>
        @endunless
    </div>

    @if($protectionOfferte)
        <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">🎁 Tu as été parrainé : ta protection acheteur est offerte sur ton 1er achat.</p>
    @endif

    @if($peutSaisir)
        <div class="mt-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="font-bold text-gray-900">Un ami t’a invité ?</p>
            <p class="mt-0.5 text-sm text-gray-600">Saisis son code (possible pendant {{ config('referral.delai_saisie_jours') }} jours après ton inscription, avant ton 1er achat).</p>
            <form method="POST" action="{{ route('parrainage.saisir') }}" class="mt-3 flex gap-2">@csrf
                <label for="code" class="sr-only">Code de parrainage</label>
                <input id="code" name="code" value="{{ old('code') }}" maxlength="20" placeholder="Ex : LEA974" autocapitalize="characters"
                       class="min-w-0 flex-1 rounded-xl border border-gray-200 px-3 py-2.5 uppercase focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                <button class="rounded-xl bg-teal-600 px-4 font-bold text-white">Valider</button>
            </form>
            @error('code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @endif

    {{-- Mes filleuls --}}
    <div class="mt-6 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-extrabold text-gray-900">Mes filleuls</h2>
            <a href="{{ route('parrainage.portefeuille') }}" class="text-sm font-semibold text-teal-700">Portefeuille : {{ $euros($solde) }} →</a>
        </div>

        <div class="mt-3">
            @if($moi->ambassadeur_at)
                <p class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-bold text-white">💚 Tu es Ambassadeur Swap’Îles ! Le badge est visible sur ton profil.</p>
            @else
                <div class="flex justify-between text-sm"><span class="font-semibold text-gray-700">Badge Ambassadeur</span><span class="text-gray-500">{{ $valides }} / {{ $palier }} amis validés</span></div>
                <div class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, $palier > 0 ? $valides / $palier * 100 : 0) }}%"></div>
                </div>
            @endif
            @if($totalGagne > 0)
                <p class="mt-3 text-sm text-gray-700">Total gagné : <strong>{{ $euros($totalGagne) }}</strong></p>
            @endif
        </div>

        @if($filleuls->isEmpty())
            <p class="mt-4 rounded-xl bg-gray-50 p-4 text-center text-sm text-gray-500">Pas encore de filleul. Partage ton lien sur WhatsApp, c’est le plus efficace 😉</p>
        @else
            <ul class="mt-4 divide-y divide-gray-100">
                @foreach($filleuls as $f)
                    @php [$libelle, $classe] = $badges[$f->statut] ?? [$f->statut, 'bg-gray-100']; @endphp
                    <li class="flex items-center justify-between gap-3 py-2.5 text-sm">
                        <span class="font-semibold text-gray-900">{{ \App\Support\ParrainageService::prenom($f->filleul) }}</span>
                        <span class="flex items-center gap-2">
                            @if($f->statut === 'valide' && $f->montant_centimes > 0)<span class="font-bold text-emerald-700">+{{ $euros($f->montant_centimes) }}</span>@endif
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $classe }}">{{ $libelle }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <p class="mt-6 text-center text-xs text-gray-500"><a href="{{ route('parrainage.conditions') }}" class="underline">Conditions du parrainage</a></p>
</section>

<script>
(function () {
    var racine = document.querySelector('[data-parrainage]');
    if (!racine) return;
    var message = @json($message);
    var lien = @json($lien);
    var jeton = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function noter(canal) {
        try {
            fetch(racine.dataset.partageUrl, { method: 'POST', credentials: 'same-origin', keepalive: true,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': jeton },
                body: JSON.stringify({ canal: canal }) });
            if (window.gtag) window.gtag('event', 'referral_link_shared', { canal: canal });
        } catch (e) {}
    }
    function bulle(texte) {
        var b = document.createElement('div');
        b.className = 'fixed left-1/2 top-24 z-[80] -translate-x-1/2 rounded-full bg-gray-900 px-5 py-3 text-sm font-bold text-white shadow-2xl';
        b.textContent = texte; document.body.appendChild(b);
        setTimeout(function () { b.remove(); }, 2400);
    }
    function copier(texte, confirmation) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texte).then(function () { bulle(confirmation); }, function () { window.prompt('Copie ce texte :', texte); });
        } else { window.prompt('Copie ce texte :', texte); }
    }
    // Menu de partage du téléphone (appli ou navigateur mobile).
    function partageNatif() {
        var cap = window.Capacitor;
        if (cap && cap.isNativePlatform && cap.isNativePlatform() && cap.Plugins && cap.Plugins.Share) {
            return cap.Plugins.Share.share({ title: 'Swap’Îles', text: message }).then(function () { return true; }, function () { return true; });
        }
        if (navigator.share) {
            return navigator.share({ title: 'Swap’Îles', text: message }).then(function () { return true; }, function () { return true; });
        }
        return Promise.resolve(false);
    }
    var dansAppli = !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform());

    racine.querySelectorAll('[data-partager]').forEach(function (el) {
        el.addEventListener('click', function (ev) {
            var canal = el.dataset.partager;
            noter(canal);
            // Dans l'appli, les liens WhatsApp / SMS ne peuvent pas s'ouvrir : menu du téléphone.
            if ((canal === 'whatsapp' || canal === 'sms') && !dansAppli) return;
            ev.preventDefault();
            partageNatif().then(function (ok) {
                if (!ok) copier(message, canal === 'instagram' ? '✅ Message copié : colle-le dans Instagram' : '✅ Message copié');
            });
        });
    });
    racine.querySelectorAll('[data-copier]').forEach(function (b) {
        b.addEventListener('click', function () { noter('copie'); copier(lien, '✅ Lien copié'); });
    });
})();
</script>
@endsection
