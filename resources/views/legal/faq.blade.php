@extends('layouts.app')

@section('title', 'FAQ — Questions fréquentes | Swap\'Îles')
@section('meta_description', 'Toutes les réponses sur Swap\'Îles : acheter, vendre, échanger, paiement sécurisé, livraison Colissimo, remise en main propre et vente inter-îles.')

@php
    $faq = [
        ['q' => 'Comment acheter en toute sécurité ?', 'a' => 'Choisissez un article, payez par carte bancaire : votre argent est sécurisé et n\'est versé au vendeur qu\'une fois l\'article reçu. Vous êtes couvert par la protection acheteur.'],
        ['q' => 'Comment vendre un article ?', 'a' => 'Cliquez sur « Déposer une annonce », ajoutez vos photos, une description, le prix, et choisissez vos options de livraison (Colissimo et/ou remise en main propre). Publiez : c\'est gratuit !'],
        ['q' => 'Qu\'est-ce que la protection acheteur ?', 'a' => 'Pour les paiements par carte, une petite protection est incluse : elle sécurise la transaction et garantit que le vendeur n\'est payé qu\'après réception de l\'article. Elle ne s\'applique qu\'au paiement en ligne (CB).'],
        ['q' => 'Comment fonctionne la livraison Colissimo ?', 'a' => 'Si le vendeur active Colissimo, un bordereau d\'expédition est généré automatiquement après l\'achat. Le vendeur dépose le colis, l\'acheteur suit sa livraison, et le paiement est libéré à la réception.'],
        ['q' => 'Puis-je acheter/vendre entre îles différentes ?', 'a' => 'Oui, mais uniquement via Colissimo : la remise en main propre n\'est pas possible entre deux îles. Un vendeur peut rendre son article disponible sur d\'autres îles à condition d\'avoir activé Colissimo.'],
        ['q' => 'Comment fonctionne la remise en main propre ?', 'a' => 'Sur une même île, vous pouvez convenir d\'un rendez-vous avec le vendeur pour récupérer l\'article et régler (en espèces ou selon accord). Simple, rapide et sans frais de port.'],
        ['q' => 'Comment fonctionne l\'échange ?', 'a' => 'Si le vendeur l\'autorise, vous pouvez proposer un de vos articles en échange (avec photo et description) directement depuis l\'annonce. Le vendeur accepte ou refuse votre proposition.'],
        ['q' => 'Comment suis-je payé en tant que vendeur ?', 'a' => 'Activez votre compte de paiement (Stripe) depuis votre profil pour recevoir vos ventes par carte directement sur votre compte bancaire, en toute sécurité.'],
        ['q' => 'Quels sont les frais ?', 'a' => 'La publication d\'annonces est gratuite. Une commission s\'applique sur les ventes en ligne pour couvrir la sécurisation du paiement. Le détail est indiqué au moment de la vente.'],
        ['q' => 'Comment contacter un vendeur ?', 'a' => 'Depuis n\'importe quelle annonce, cliquez sur « Envoyer un message » pour discuter directement avec le vendeur via la messagerie intégrée.'],
    ];
@endphp

@push('structured_data')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => collect($faq)->map(fn ($item) => [
        '@type' => 'Question',
        'name' => $item['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
    ])->all(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
<section class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-8">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900">❓ Questions fréquentes</h1>
            <p class="mt-2 text-gray-500">Tout ce qu'il faut savoir pour acheter, vendre et échanger sur Swap'Îles.</p>
        </div>

        <div class="space-y-3">
            @foreach($faq as $item)
                <details class="group rounded-2xl border border-gray-100 bg-white shadow-sm">
                    <summary class="flex cursor-pointer items-center justify-between gap-3 p-5 font-bold text-gray-900 list-none">
                        <span>{{ $item['q'] }}</span>
                        <span class="shrink-0 text-teal-600 transition group-open:rotate-45 text-xl">+</span>
                    </summary>
                    <div class="px-5 pb-5 text-gray-600 leading-relaxed">{{ $item['a'] }}</div>
                </details>
            @endforeach
        </div>

        <div class="mt-8 rounded-2xl border border-teal-100 bg-teal-50 p-6 text-center">
            <p class="font-bold text-gray-900">Vous n'avez pas trouvé votre réponse ?</p>
            <p class="mt-1 text-sm text-gray-600">Écrivez-nous, on est là pour vous aider.</p>
            <a href="mailto:contact@swapiles.com" class="mt-4 inline-flex rounded-xl bg-teal-600 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-700">contact@swapiles.com</a>
        </div>
    </div>
</section>
@endsection
