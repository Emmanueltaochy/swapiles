@extends('layouts.app')

@section('title', $prenom . ' t’invite sur Swap’Îles 🌴')
@section('meta_description', 'Inscris-toi avec le lien de ' . $prenom . ' : tes frais de protection sont offerts sur ton 1er achat sur Swap’Îles.')

@section('content')
<section class="mx-auto max-w-md px-4 py-10 text-center">
    <div class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-teal-600 text-3xl font-extrabold text-white shadow-lg ring-4 ring-teal-100">
        {{ mb_strtoupper(mb_substr($parrain->name, 0, 1)) }}
    </div>
    <h1 class="mt-5 text-2xl font-extrabold text-gray-900 sm:text-3xl">{{ $prenom }} t’invite sur Swap’Îles 🌴</h1>
    <p class="mt-3 text-gray-600">La seconde main entre voisins des îles : achète, vends, échange en toute sécurité.</p>

    <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-left">
        <p class="font-bold text-emerald-900">🎁 Ton cadeau de bienvenue</p>
        <p class="mt-1 text-sm text-emerald-900">Tes <strong>frais de protection acheteur sont offerts</strong> sur ton 1er achat payé dans l’appli.</p>
        <p class="mt-3 text-sm text-emerald-800">Code de parrainage : <strong class="font-mono text-base tracking-wider" data-code>{{ $code }}</strong></p>
    </div>

    @if($moi)
        @if($moi->id === $parrain->id)
            <p class="mt-6 rounded-xl bg-gray-50 p-4 text-sm text-gray-600">C’est ton propre lien 😉 Partage-le à tes amis depuis <a href="{{ route('parrainage.index') }}" class="font-semibold text-teal-700">Inviter des amis</a>.</p>
        @elseif($peutSaisir)
            <form method="POST" action="{{ route('parrainage.saisir') }}" class="mt-6">@csrf
                <input type="hidden" name="code" value="{{ $code }}">
                <button class="w-full rounded-xl bg-teal-600 px-5 py-3.5 font-bold text-white hover:bg-teal-700">Utiliser le code de {{ $prenom }}</button>
            </form>
        @else
            <p class="mt-6 rounded-xl bg-gray-50 p-4 text-sm text-gray-600">Tu as déjà un compte : le code de parrainage ne s’utilise qu’au moment de l’inscription (ou dans les 7 jours, avant ton 1er achat).</p>
        @endif
    @else
        <div class="mt-6 space-y-3">
            @if($store)
                <a href="{{ $store }}" data-store class="block w-full rounded-xl bg-gray-900 px-5 py-3.5 font-bold text-white">
                    {{ $plateforme === 'ios' ? ' Télécharger sur l’App Store' : '▶ Télécharger sur Google Play' }}
                </a>
                <a href="{{ route('register') }}" class="block w-full rounded-xl border-2 border-teal-600 px-5 py-3 font-bold text-teal-700">Continuer sur le site</a>
                <p class="text-xs text-gray-500">Ton code est mémorisé. S’il n’apparaît pas à l’inscription dans l’appli, saisis <strong>{{ $code }}</strong> (il est aussi copié quand tu touches « Télécharger »).</p>
            @else
                <a href="{{ route('register') }}" class="block w-full rounded-xl bg-teal-600 px-5 py-3.5 font-bold text-white hover:bg-teal-700">Je m’inscris</a>
                <p class="text-sm text-gray-500">Déjà inscrit ? <a href="{{ route('login') }}" class="font-semibold text-teal-700">Se connecter</a></p>
            @endif
        </div>
    @endif

    <p class="mt-8 text-xs text-gray-400"><a href="{{ route('parrainage.conditions') }}" class="underline">Conditions du parrainage</a></p>
</section>

<script>
(function () {
    var code = @json($code);
    // Le code est copié en allant au store : collable à l'inscription dans l'appli.
    document.querySelectorAll('[data-store]').forEach(function (a) {
        a.addEventListener('click', function () {
            try { if (navigator.clipboard) navigator.clipboard.writeText(code); } catch (e) {}
        });
    });
    @if($redirectionStore && $store)
        setTimeout(function () { window.location.href = @json($store); }, 1800);
    @endif
})();
</script>
@endsection
