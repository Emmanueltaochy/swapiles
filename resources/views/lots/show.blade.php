@extends('layouts.app')

@section('title', 'Mon lot chez ' . $vendeur->name . ' — Swap\'Îles')

@push('head')
    <meta name="turbo-visit-control" content="reload">
@endpush

@section('content')
@php
    $euros = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €';
    $defaut = old('delivery_method', $modes['hand_delivery'] ? 'hand_delivery' : ($modes['colissimo'] ? 'colissimo' : ($modes['relay']->isNotEmpty() ? 'relay' : '')));
    $moi = auth()->user();
@endphp
<section class="bg-gray-50 min-h-screen py-6 sm:py-8">
    <div class="max-w-3xl mx-auto px-4">
        <a href="{{ route('profiles.show', $vendeur) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">← Dressing de {{ $vendeur->name }}</a>
        <h1 class="mt-2 text-2xl sm:text-3xl font-extrabold text-gray-900">🛍️ Mon lot chez {{ $vendeur->name }}</h1>
        <p class="mt-1 text-gray-500">Un seul paiement, une seule livraison{{ $remisePct > 0 ? ', et −' . $remisePct . ' % sur tout le lot' : '' }}.</p>

        @if(session('status'))
            <div class="mt-4 rounded-2xl bg-teal-50 p-4 text-sm font-semibold text-teal-800">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="mt-4 rounded-2xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>
        @endif

        {{-- Les articles --}}
        <div class="mt-5 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            @foreach($articles as $article)
                <div class="flex items-center gap-3 border-b border-gray-100 p-3 last:border-b-0">
                    <a href="{{ route('listings.show', $article) }}" class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-gray-100">
                        @if($article->images->first())
                            <img src="{{ $article->images->sortBy('order')->first()->url }}" alt="{{ $article->title }}" class="h-full w-full object-cover">
                        @else
                            <span class="grid h-full w-full place-items-center text-2xl text-gray-300">📦</span>
                        @endif
                    </a>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-gray-900">{{ $article->title }}</p>
                        <p class="text-sm font-bold text-gray-900">{{ $euros($article->price) }}</p>
                    </div>
                    <form method="POST" action="{{ route('lots.retirer', $article) }}">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-500 hover:bg-gray-100 hover:text-red-600" aria-label="Retirer {{ $article->title }} du lot">Retirer</button>
                    </form>
                </div>
            @endforeach
            <a href="{{ route('profiles.show', $vendeur) }}" class="block bg-gray-50 px-4 py-3 text-center text-sm font-bold text-teal-700 hover:bg-teal-50">
                + Ajouter d’autres articles de {{ $vendeur->name }}
            </a>
        </div>

        @if($prochainPalier)
            <p class="mt-3 rounded-xl bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-900 ring-1 ring-amber-200">🎁 {{ $prochainPalier }}</p>
        @endif

        @if($articles->count() < 2)
            <p class="mt-4 rounded-2xl bg-white p-4 text-sm text-gray-600 shadow-sm">Un lot contient au moins 2 articles. Pour cet article seul, utilisez <a href="{{ route('checkout.show', $articles->first()) }}" class="font-bold text-teal-700">Acheter</a>.</p>
        @elseif($modes['aucun_mode'])
            <p class="mt-4 rounded-2xl bg-red-50 p-4 text-sm text-red-700">Ces articles n’ont pas de mode de livraison en commun : retirez-en un ou achetez-les séparément.</p>
        @else
            <form method="POST" action="{{ route('lots.payer', $vendeur) }}" class="mt-5 space-y-4" data-lot-form>
                @csrf

                {{-- Livraison --}}
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <h2 class="font-bold text-gray-900">Livraison <span class="font-normal text-gray-500">· un seul envoi pour tout le lot</span></h2>
                    <div class="mt-3 space-y-2">
                        @if($modes['hand_delivery'])
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-3 has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50">
                                <input type="radio" name="delivery_method" value="hand_delivery" @checked($defaut === 'hand_delivery') class="text-teal-600">
                                <span class="flex-1"><span class="font-semibold text-gray-900">🤝 Remise en main propre</span><span class="block text-xs text-gray-500">Avec {{ $vendeur->name }}, lieu à convenir</span></span>
                                <span class="font-bold text-emerald-700">Gratuit</span>
                            </label>
                        @endif
                        @if($modes['relay']->isNotEmpty())
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-3 has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50">
                                <input type="radio" name="delivery_method" value="relay" @checked($defaut === 'relay') class="text-teal-600">
                                <span class="flex-1"><span class="font-semibold text-gray-900">🏪 Point relais</span><span class="block text-xs text-gray-500">Vous retirez le colis avec un code</span></span>
                                <span class="font-semibold text-gray-900">{{ $euros(config('pricing.relay_fee', 3)) }}</span>
                            </label>
                            <div data-section="relay" class="pl-2">
                                <select name="relay_point_id" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm">
                                    @foreach($modes['relay'] as $rp)
                                        <option value="{{ $rp->id }}" @selected((string) old('relay_point_id') === (string) $rp->id)>{{ $rp->name }} — {{ $rp->city }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        @if($modes['colissimo'])
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-3 has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50">
                                <input type="radio" name="delivery_method" value="colissimo" @checked($defaut === 'colissimo') class="text-teal-600">
                                <span class="flex-1"><span class="font-semibold text-gray-900">📦 Colissimo à domicile</span><span class="block text-xs text-gray-500">Un seul colis de {{ number_format($modes['poids'], 1, ',', ' ') }} kg · prix calculé à l’étape suivante</span></span>
                            </label>
                            <div data-section="colissimo" class="grid grid-cols-1 gap-3 pl-2 sm:grid-cols-2">
                                <input name="buyer_full_name" value="{{ old('buyer_full_name', $moi->name) }}" placeholder="Nom complet" class="rounded-xl border border-gray-200 px-4 py-3 text-sm sm:col-span-2">
                                <input name="buyer_phone" value="{{ old('buyer_phone', $moi->phone) }}" placeholder="Téléphone" class="rounded-xl border border-gray-200 px-4 py-3 text-sm sm:col-span-2">
                                <input name="shipping_address_line1" value="{{ old('shipping_address_line1', $moi->address_line1) }}" placeholder="Adresse" class="rounded-xl border border-gray-200 px-4 py-3 text-sm sm:col-span-2">
                                <input name="shipping_address_line2" value="{{ old('shipping_address_line2', $moi->address_line2) }}" placeholder="Complément (facultatif)" class="rounded-xl border border-gray-200 px-4 py-3 text-sm sm:col-span-2">
                                <input name="shipping_postal_code" value="{{ old('shipping_postal_code', $moi->postal_code) }}" placeholder="Code postal" class="rounded-xl border border-gray-200 px-4 py-3 text-sm">
                                <input name="shipping_city" value="{{ old('shipping_city', $moi->city) }}" placeholder="Ville" class="rounded-xl border border-gray-200 px-4 py-3 text-sm">
                                <select name="shipping_territory" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm sm:col-span-2">
                                    @foreach(['reunion' => 'La Réunion', 'guyane' => 'Guyane', 'martinique' => 'Martinique', 'guadeloupe' => 'Guadeloupe', 'mayotte' => 'Mayotte', 'metropole' => 'France métropolitaine'] as $cle => $nom)
                                        <option value="{{ $cle }}" @selected(old('shipping_territory', $moi->territoire === $nom ? $cle : null) === $cle)>{{ $nom }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Récapitulatif --}}
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm text-sm">
                    <div class="flex justify-between py-1"><span class="text-gray-500">{{ $articles->count() }} articles</span><span class="font-semibold">{{ $euros($sousTotal) }}</span></div>
                    @if($remise > 0)
                        <div class="flex justify-between py-1 text-emerald-700"><span>🎁 Remise lot −{{ $remisePct }} %</span><span class="font-bold">− {{ $euros($remise) }}</span></div>
                    @endif
                    <div class="flex justify-between py-1">
                        <span class="text-gray-500">Protection acheteur 🛡️ <span class="text-xs">(une seule fois)</span></span>
                        @if($protectionOfferte > 0)
                            <span class="font-semibold text-emerald-700">offerte 🎁</span>
                        @else
                            <span class="font-semibold">{{ $euros($protection) }}</span>
                        @endif
                    </div>
                    @if($creditDisponible > 0)
                        <label class="mt-1 flex items-center gap-2 py-1 text-gray-600">
                            <input type="checkbox" name="utiliser_credit" value="1" checked class="rounded text-teal-600">
                            Utiliser mon crédit Swap’Îles ({{ $euros($creditDisponible / 100) }})
                        </label>
                    @endif
                    <p class="mt-2 border-t border-gray-100 pt-2 text-xs text-gray-500">La livraison et le total exact s’affichent à l’étape suivante, avant de payer.</p>
                </div>

                <button class="w-full rounded-2xl bg-teal-600 px-6 py-4 text-base font-bold text-white shadow-sm hover:bg-teal-700">Continuer vers le paiement</button>
                <p class="text-center text-xs text-gray-500">🛡️ Paiement protégé : {{ $vendeur->name }} n’est payé qu’après réception.</p>
            </form>

            <script>
                (function () {
                    var form = document.querySelector('[data-lot-form]');
                    if (!form) return;
                    function maj() {
                        var choix = (form.querySelector('input[name="delivery_method"]:checked') || {}).value;
                        form.querySelectorAll('[data-section]').forEach(function (s) {
                            var actif = s.getAttribute('data-section') === choix;
                            s.hidden = !actif;
                            s.querySelectorAll('input, select').forEach(function (c) { c.disabled = !actif; });
                        });
                    }
                    form.addEventListener('change', maj);
                    maj();
                })();
            </script>
        @endif
    </div>
</section>
@endsection
