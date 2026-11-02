@extends('layouts.app')

@section('title', 'Bon de commande n° ' . $t->id . ' — Swap’Îles')
@section('robots', 'noindex, nofollow')

@section('content')
@php
    $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €';
    $pro = $t->seller;
    $article = (float) $t->seller_amount + (float) $t->commission - ($t->delivery_method === 'livraison_pro' ? (float) $t->shipping_fee : 0.0);
    $livraison = (float) $t->shipping_fee + (float) $t->relay_fee;
    $mode = match ($t->delivery_method) { 'hand_delivery' => 'Retrait en boutique', 'livraison_pro' => 'Livraison par la boutique', 'colissimo' => 'Colissimo', 'relay' => 'Point relais', default => $t->delivery_method };
@endphp
<div class="mx-auto max-w-2xl px-4 py-8">
    <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm print:border-0 print:shadow-none">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bon de commande</p>
                <h1 class="text-2xl font-extrabold text-gray-900">N° {{ $t->id }}</h1>
                <p class="text-sm text-gray-500">du {{ $t->created_at->format('d/m/Y') }}</p>
            </div>
            <button type="button" onclick="window.print()" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-bold text-white print:hidden">🖨️ Imprimer</button>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl bg-gray-50 p-4 text-sm">
                <p class="text-xs font-semibold uppercase text-gray-500">Vendeur</p>
                <p class="mt-1 font-bold text-gray-900">{{ $pro?->pro_raison_sociale }}</p>
                <p class="text-gray-600">SIRET {{ \App\Support\ComptePro::siret($pro?->pro_siret) }}</p>
                @if($pro?->pro_adresse)<p class="text-gray-600">{{ $pro->pro_adresse }}</p>@endif
                @if($pro?->pro_telephone)<p class="text-gray-600">{{ $pro->pro_telephone }}</p>@endif
            </div>
            <div class="rounded-2xl bg-gray-50 p-4 text-sm">
                <p class="text-xs font-semibold uppercase text-gray-500">Client</p>
                <p class="mt-1 font-bold text-gray-900">{{ $t->buyer_full_name ?: $t->buyer?->name }}</p>
                @if($t->shipping_address_line1)
                    <p class="text-gray-600">{{ $t->shipping_address_line1 }}</p>
                    <p class="text-gray-600">{{ $t->shipping_postal_code }} {{ $t->shipping_city }}</p>
                @endif
            </div>
        </div>

        <table class="mt-6 w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                <tr><td class="py-2 text-gray-700">{{ $t->listing?->title ?? 'Article' }}</td><td class="py-2 text-right font-semibold">{{ $eur($article) }}</td></tr>
                <tr><td class="py-2 text-gray-700">{{ $mode }}</td><td class="py-2 text-right font-semibold">{{ $livraison > 0 ? $eur($livraison) : 'Offert' }}</td></tr>
                <tr><td class="py-3 font-extrabold text-gray-900">Total TTC payé</td><td class="py-3 text-right text-lg font-extrabold text-gray-900">{{ $eur($article + $livraison) }}</td></tr>
            </tbody>
        </table>
        <p class="text-xs text-gray-500">Paiement par carte via Swap’Îles le {{ $t->created_at->format('d/m/Y') }}.</p>

        @if($estVendeur)
            <div class="mt-4 rounded-2xl bg-teal-50 p-4 text-sm text-teal-900">
                <p>Commission Swap’Îles : <b>− {{ $eur($t->commission) }}</b> · Vous recevez : <b>{{ $eur($t->seller_amount) }}</b></p>
                <p class="mt-1 text-xs">Pensez à émettre la facture de cette vente à votre client.</p>
            </div>
        @endif

        <div class="mt-6 space-y-1 border-t border-gray-100 pt-4 text-xs text-gray-500">
            <p><b>Droit de rétractation :</b> vous disposez de 14 jours à compter de la réception de l’article pour vous rétracter, sans avoir à vous justifier (hors exceptions légales). Contactez le vendeur par la messagerie Swap’Îles.</p>
            <p><b>Garanties :</b> l’article bénéficie de la garantie légale de conformité et de la garantie contre les vices cachés.</p>
            @if($pro?->pro_retours)<p><b>Conditions du vendeur :</b> {{ $pro->pro_retours }}</p>@endif
            <p>Ce document n’est pas une facture : la facture est émise par le vendeur.</p>
        </div>
    </div>
</div>
@endsection
