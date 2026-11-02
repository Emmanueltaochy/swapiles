@extends('layouts.app')

@section('title', 'Vendeurs professionnels — conditions — Swap\'Îles')

@section('content')
@php $taux = str_replace('.', ',', round((float) config('pricing.pro_commission_rate', 0.05) * 100, 2)); @endphp
<section class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 sm:p-10 shadow-sm text-gray-700 leading-relaxed space-y-4">
            <h1 class="text-3xl font-extrabold text-gray-900">Vendeurs professionnels sur Swap’Îles</h1>
            <p class="text-sm text-gray-400">Conditions applicables aux ventes réalisées par des professionnels · dernière mise à jour : {{ \Illuminate\Support\Carbon::parse('2026-11-02')->locale('fr')->translatedFormat('j F Y') }}</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">1. Qui est vendeur professionnel ?</h2>
            <p>Un vendeur professionnel est une entreprise (commerçant, artisan, marque…) dont le compte a été vérifié par Swap’Îles. Ses annonces et son profil portent la mention <strong>« Vendeur professionnel »</strong>, avec sa raison sociale et son numéro SIRET. Swap’Îles est une place de marché : le contrat de vente est conclu directement entre le professionnel et l’acheteur.</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">2. Pour l’acheteur</h2>
            <ul class="list-disc pl-6 space-y-1">
                <li><strong>Prix affiché = prix payé.</strong> Les prix sont indiqués toutes taxes comprises. Aucun frais de service n’est ajouté par Swap’Îles ; seule la livraison choisie s’ajoute, et son prix est affiché avant le paiement.</li>
                <li><strong>Droit de rétractation de 14 jours</strong> à compter de la réception de l’article, sans avoir à vous justifier (articles 221-18 et suivants du Code de la consommation), sauf exceptions légales (articles personnalisés, produits d’hygiène descellés après la livraison, etc.). Pour l’exercer, contactez le vendeur par la messagerie Swap’Îles. Le vendeur vous rembourse au plus tard 14 jours après avoir été informé de votre décision ; il peut attendre de récupérer l’article.</li>
                <li><strong>Garanties légales :</strong> garantie légale de conformité (2 ans) et garantie contre les vices cachés.</li>
                <li><strong>Remise de l’article</strong> selon le mode choisi : retrait en boutique avec un code personnel, livraison par le vendeur, Colissimo ou point relais.</li>
                <li><strong>Facture :</strong> elle est émise par le vendeur. Un bon de commande est disponible dans votre espace pour chaque achat.</li>
                <li><strong>Litige :</strong> écrivez d’abord au vendeur, puis à Swap’Îles (contact@swapiles.com) si aucune solution n’est trouvée. Vous pouvez aussi recourir gratuitement à un médiateur de la consommation.</li>
            </ul>

            <h2 class="text-xl font-bold text-gray-900 pt-4">3. Pour le vendeur professionnel</h2>
            <ul class="list-disc pl-6 space-y-1">
                <li><strong>Commission :</strong> Swap’Îles retient une commission sur le prix de chaque article vendu par carte ({{ $taux }} % par défaut, ou le taux convenu avec Swap’Îles). Elle n’est pas facturée à l’acheteur. Aucun abonnement n’est demandé sans accord écrit.</li>
                <li><strong>Versement :</strong> le paiement est encaissé par Swap’Îles via Stripe, puis versé au vendeur, déduction faite de la commission : dès la validation du code de retrait en boutique, à la confirmation de réception par le client, ou au plus tard {{ (int) config('pricing.pro_versement_auto_jours', 7) }} jours après l’expédition.</li>
                <li><strong>Obligations du vendeur :</strong> informations exactes sur les articles et les prix TTC, respect du droit de rétractation et des garanties légales, émission des factures, déclaration de son chiffre d’affaires et de la TVA. Le vendeur indique ses éventuelles conditions de retour, qui ne peuvent pas réduire les droits légaux de l’acheteur.</li>
                <li><strong>Ventes en direct (lives) :</strong> sur autorisation de Swap’Îles. Le vendeur reste responsable des propos et des articles présentés.</li>
                <li><strong>Résiliation :</strong> le vendeur peut cesser d’utiliser le compte professionnel à tout moment ; Swap’Îles peut le suspendre en cas de manquement, après information du vendeur.</li>
            </ul>

            <h2 class="text-xl font-bold text-gray-900 pt-4">4. Rôle de Swap’Îles</h2>
            <p>Swap’Îles met à disposition la place de marché, le paiement sécurisé, les outils de remise et de messagerie. Swap’Îles n’est pas le vendeur des articles des professionnels. Le classement des annonces ne dépend d’aucune rémunération : les annonces des professionnels apparaissent selon les mêmes règles que celles des particuliers.</p>

            <p class="pt-4 text-sm text-gray-500">Voir aussi : <a href="{{ route('legal.cgu') }}" class="font-semibold text-teal-700 underline">conditions générales d’utilisation</a> · <a href="{{ route('legal.cgv') }}" class="font-semibold text-teal-700 underline">conditions générales de vente</a> · <a href="{{ route('legal.mentions') }}" class="font-semibold text-teal-700 underline">mentions légales</a>.</p>
        </div>
    </div>
</section>
@endsection
