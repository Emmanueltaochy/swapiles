@extends('layouts.app')

@section('title', 'Commande — Swap\'Îles')

@section('content')
@php
    $itemAmount = $itemAmount ?? ($offer ? (int) $offer->amount : (int) $listing->price);
    $canColissimo = ($listing->requires_online_payment ?? false) && ($listing->allows_colissimo ?? false);
    $canHandDelivery = ($listing->allows_hand_delivery ?? $listing->pickup_enabled ?? true);
    $defaultDelivery = old('delivery_method', $canHandDelivery ? 'hand_delivery' : ($canColissimo ? 'colissimo' : ''));
@endphp

<section class="bg-gray-50 min-h-screen py-8 sm:py-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6">

        <h1 class="mb-6 text-2xl sm:text-3xl font-bold text-gray-900">Vérifier ma commande</h1>

        @if($errors->any())
            <div class="mb-6 rounded-xl bg-red-50 p-4 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Article (1er mobile, à droite desktop, sticky) --}}
            <aside class="order-1 lg:order-2">
                <div class="lg:sticky lg:top-24 space-y-4">
                    <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm">
                        <h2 class="mb-4 font-semibold text-gray-900">Article</h2>
                        <div class="flex gap-4">
                            <div class="h-28 w-24 shrink-0 overflow-hidden rounded-xl bg-gray-100">
                                @if($listing->images->first())
                                    <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" class="h-full w-full object-cover">
                                @else
                                    <div class="grid h-full w-full place-items-center text-4xl text-gray-300" aria-hidden="true">📦</div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-gray-900">{{ $listing->title }}</h3>
                                <p class="mt-1 text-sm text-gray-500">Vendu par {{ $listing->user->name ?? 'un membre Swap’Îles' }}</p>
                                @if($offer)
                                    <p class="mt-3 text-xs font-semibold text-emerald-700">✅ Offre acceptée</p>
                                    <p class="text-sm text-gray-400 line-through">{{ number_format($listing->price, 0, ',', ' ') }} €</p>
                                @endif
                                <p class="mt-1 text-2xl font-bold text-teal-700">{{ number_format($itemAmount, 0, ',', ' ') }} €</p>
                            </div>
                        </div>

                        <div class="mt-5 space-y-2 border-t border-gray-100 pt-4 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Prix article</span>
                                <span class="font-semibold text-gray-900">{{ number_format($itemAmount, 0, ',', ' ') }} €</span>
                            </div>
                            <p class="text-xs text-gray-400">Protection acheteur et livraison calculées à l'étape suivante.</p>
                        </div>
                    </div>
                </div>
            </aside>

            {{-- Mode de remise (2e mobile, à gauche desktop) --}}
            <div class="order-2 lg:order-1">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm">
                    <h2 class="mb-4 font-semibold text-gray-900">Mode de remise</h2>

                    <form method="POST" action="{{ route('checkout.start', $listing) }}" class="space-y-4">
                        @csrf
                        @if($offer)
                            <input type="hidden" name="offer" value="{{ $offer->id }}">
                        @endif

                        <div class="space-y-3">
                            @if($canHandDelivery)
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 transition has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50/40">
                                    <input type="radio" name="delivery_method" value="hand_delivery" data-delivery
                                           class="mt-1 text-teal-600 focus:ring-teal-500" @checked($defaultDelivery === 'hand_delivery')>
                                    <span>
                                        <span class="block font-semibold text-gray-900">🤝 Remise en main propre</span>
                                        <span class="mt-1 block text-sm text-gray-500">
                                            Rendez-vous proposé :
                                            <span class="font-medium text-gray-700">{{ $listing->hand_delivery_location ?: $listing->location_address ?: 'à définir avec le vendeur' }}</span>
                                        </span>
                                    </span>
                                </label>
                            @endif

                            @if($canColissimo)
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 transition has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50/40">
                                    <input type="radio" name="delivery_method" value="colissimo" data-delivery
                                           class="mt-1 text-teal-600 focus:ring-teal-500" @checked($defaultDelivery === 'colissimo')>
                                    <span>
                                        <span class="block font-semibold text-gray-900">📦 Livraison Colissimo</span>
                                        <span class="mt-1 block text-sm text-gray-500">Livraison à domicile. En cas d'absence, dépôt en bureau de poste ou point relais proche.</span>
                                    </span>
                                </label>
                            @endif
                        </div>

                        {{-- Adresse (affichée seulement si Colissimo) --}}
                        @if($canColissimo)
                            <div id="colissimo-address-block" class="space-y-3 rounded-xl border border-gray-100 bg-gray-50 p-4 {{ $defaultDelivery === 'colissimo' ? '' : 'hidden' }}">
                                <p class="font-semibold text-gray-900">Informations de livraison</p>
                                <input type="hidden" name="colissimo_delivery_type" value="home">

                                <input name="buyer_full_name" value="{{ old('buyer_full_name', auth()->user()->name) }}" placeholder="Nom complet *" autocomplete="name"
                                       class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                                <input name="buyer_phone" value="{{ old('buyer_phone', auth()->user()->phone ?? '') }}" placeholder="Téléphone *" autocomplete="tel"
                                       class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                                <input name="shipping_address_line1" value="{{ old('shipping_address_line1', auth()->user()->address_line1 ?? '') }}" placeholder="Adresse *" autocomplete="address-line1"
                                       class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                                <input name="shipping_address_line2" value="{{ old('shipping_address_line2', auth()->user()->address_line2 ?? '') }}" placeholder="Complément d'adresse" autocomplete="address-line2"
                                       class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100">

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <input name="shipping_postal_code" value="{{ old('shipping_postal_code', auth()->user()->postal_code ?? '') }}" placeholder="Code postal *" autocomplete="postal-code"
                                           class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                                    <input name="shipping_city" value="{{ old('shipping_city', auth()->user()->city ?? '') }}" placeholder="Ville *" autocomplete="address-level2"
                                           class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                                </div>

                                <div>
                                    <label for="shipping_territory" class="mb-1 block text-sm font-semibold text-gray-700">Territoire de livraison <span class="text-red-600">*</span></label>
                                    <select id="shipping_territory" name="shipping_territory" required
                                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                                        <option value="reunion" @selected(old('shipping_territory', auth()->user()->territoire ?? 'reunion') === 'La Réunion' || old('shipping_territory', auth()->user()->territoire ?? 'reunion') === 'reunion')>La Réunion</option>
                                        <option value="guyane" @selected(old('shipping_territory', auth()->user()->territoire ?? '') === 'Guyane' || old('shipping_territory') === 'guyane')>Guyane</option>
                                        <option value="martinique" @selected(old('shipping_territory', auth()->user()->territoire ?? '') === 'Martinique' || old('shipping_territory') === 'martinique')>Martinique</option>
                                        <option value="guadeloupe" @selected(old('shipping_territory', auth()->user()->territoire ?? '') === 'Guadeloupe' || old('shipping_territory') === 'guadeloupe')>Guadeloupe</option>
                                        <option value="mayotte" @selected(old('shipping_territory', auth()->user()->territoire ?? '') === 'Mayotte' || old('shipping_territory') === 'mayotte')>Mayotte</option>
                                        <option value="metropole" @selected(old('shipping_territory') === 'metropole')>France métropolitaine</option>
                                        <option value="international" @selected(old('shipping_territory') === 'international')>International</option>
                                    </select>
                                    <input type="hidden" name="shipping_country" value="France">
                                </div>
                            </div>
                        @endif

                        <button class="w-full rounded-xl bg-teal-600 px-6 py-4 font-semibold text-white shadow-sm transition hover:bg-teal-700 focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2">
                            Continuer vers le paiement
                        </button>

                        <p class="text-center text-xs text-gray-400">🔒 Paiement sécurisé à l'étape suivante</p>
                    </form>
                </div>
            </div>

        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Afficher le bloc adresse uniquement quand "Colissimo" est sélectionné
    const addressBlock = document.getElementById('colissimo-address-block');
    const radios = document.querySelectorAll('[data-delivery]');
    if (!addressBlock || !radios.length) return;

    function sync() {
        const selected = document.querySelector('[data-delivery]:checked');
        const isColissimo = selected && selected.value === 'colissimo';
        addressBlock.classList.toggle('hidden', !isColissimo);
    }

    radios.forEach(r => r.addEventListener('change', sync));
    sync();
});
</script>
@endsection
