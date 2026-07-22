@extends('layouts.app')

@section('title', 'Mentions légales — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 sm:p-10 shadow-sm text-gray-700 leading-relaxed space-y-4">
            <h1 class="text-3xl font-extrabold text-gray-900">Mentions légales</h1>
            <p class="text-sm text-gray-400">Dernière mise à jour : 22 juillet 2026</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">Éditeur du site</h2>
            <p>
                Le site <strong>Swap'Îles</strong> (swapiles.com) est édité par :<br>
                <strong>Emmanuel Taochy</strong> — Entreprise individuelle (EI)<br>
                Adresse : 124 route des canots, 97427 Étang-Salé, La Réunion<br>
                SIRET : 884 270 604 00025<br>
                E-mail : contact@swapiles.com<br>
                Directeur de la publication : Emmanuel Taochy
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">Hébergement</h2>
            <p>
                Le site est hébergé par : <strong>Hostinger International Ltd</strong><br>
                61 Lordou Vironos Street, 6023 Larnaca, Chypre
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">Prestataire de paiement</h2>
            <p>
                Les paiements en ligne sont opérés par <strong>Stripe Payments Europe, Ltd.</strong>
                Les livraisons sont assurées via <strong>Colissimo (La Poste)</strong>.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">Propriété intellectuelle</h2>
            <p>
                L'ensemble des éléments du site (marque, logo, textes, interface) est protégé.
                Toute reproduction sans autorisation est interdite. Les photos et contenus des annonces
                restent la responsabilité de leurs auteurs (les membres).
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">Contact</h2>
            <p>Pour toute question : <a href="mailto:contact@swapiles.com" class="font-semibold text-teal-700">contact@swapiles.com</a>.</p>

        </div>
    </div>
</section>
@endsection
