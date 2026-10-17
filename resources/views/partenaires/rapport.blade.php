@extends('layouts.app')

@section('title', 'Rapport d’impact — ' . ($point->partenaire_enseigne ?: $point->name) . ' — Swap’Îles')
@section('robots', 'noindex, nofollow')

@section('content')
@php
    $n = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $kg = fn ($v) => $v >= 1000 ? number_format($v / 1000, 1, ',', ' ') . ' t' : number_format((float) $v, 0, ',', ' ') . ' kg';
    $moisFr = fn ($m) => ucfirst($m->locale('fr')->translatedFormat('F Y'));
    $c = $chiffres;
@endphp
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-3xl mx-auto px-4 space-y-6">

        <header class="rounded-3xl bg-gradient-to-br from-teal-800 to-emerald-700 p-6 text-white shadow-sm">
            <div class="flex items-center gap-4">
                @if($point->logoUrl())
                    <img src="{{ $point->logoUrl() }}" alt="{{ $point->partenaire_enseigne }}" class="h-14 w-14 shrink-0 rounded-2xl bg-white object-contain p-1.5">
                @endif
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-teal-100">Rapport d’impact · {{ $moisFr($mois) }}</p>
                    <h1 class="text-2xl font-extrabold leading-tight">{{ $point->partenaire_enseigne ?: $point->name }}</h1>
                    <p class="text-sm text-teal-50/90">Point relais Swap’Îles · {{ $point->name }}@if($point->city), {{ $point->city }}@endif</p>
                </div>
            </div>
        </header>

        <form method="GET" class="flex items-center gap-2">
            <label for="mois" class="text-sm font-semibold text-gray-600">Mois</label>
            <input id="mois" type="month" name="mois" value="{{ $mois->format('Y-m') }}" max="{{ now()->format('Y-m') }}"
                   class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm" onchange="this.form.submit()">
        </form>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach([
                ['📦', 'Colis', $n($c['colis']), 'confiés à votre magasin'],
                ['🚶', 'Passages en magasin', $n($c['passages']), 'dépôts et retraits'],
                ['♻️', 'Articles réemployés', $n($c['articles']), 'une seconde vie'],
                ['🌍', 'CO₂ évité', $kg($c['co2']), '≈ ' . $n($c['km']) . ' km en voiture'],
            ] as [$emoji, $libelle, $valeur, $sous])
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold text-gray-500">{{ $emoji }} {{ $libelle }}</p>
                    <p class="mt-1 text-2xl font-extrabold text-gray-900">{{ $valeur }}</p>
                    <p class="text-xs text-gray-500">{{ $sous }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="font-extrabold text-gray-900">Les 6 derniers mois</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-gray-500"><th class="py-2">Mois</th><th class="py-2 text-right">Colis</th><th class="py-2 text-right">Passages</th><th class="py-2 text-right">Déchets évités</th><th class="py-2 text-right">CO₂ évité</th></tr></thead>
                    <tbody>
                        @foreach(array_reverse($synthese['historique']) as $m)
                            <tr class="border-t border-gray-100">
                                <td class="py-2 font-semibold">{{ $moisFr($m['mois']) }}</td>
                                <td class="py-2 text-right">{{ $n($m['colis']) }}</td>
                                <td class="py-2 text-right">{{ $n($m['passages']) }}</td>
                                <td class="py-2 text-right">{{ $kg($m['kg']) }}</td>
                                <td class="py-2 text-right">{{ $kg($m['co2']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 rounded-xl bg-teal-50 p-3 text-sm text-teal-900">
                <strong>Depuis {{ $point->partenaire_depuis ? 'le ' . $point->partenaire_depuis->format('d/m/Y') : 'le début' }} :</strong>
                {{ $n($synthese['cumul']['colis']) }} colis, {{ $n($synthese['cumul']['passages']) }} passages en magasin,
                {{ $kg($synthese['cumul']['kg']) }} de déchets et {{ $kg($synthese['cumul']['co2']) }} de CO₂ évités.
            </p>
        </div>

        <p class="text-xs leading-relaxed text-gray-500">
            Méthode : chaque colis est un article d’occasion acheté sur Swap’Îles et remis dans votre magasin. Déchets et CO₂ évités : estimations
            moyennes par catégorie d’article (fabrication d’un article neuf évitée), à titre indicatif. Ce rapport ne contient aucune donnée personnelle.
            Merci de faire vivre le réemploi sur l’île 🌴 — L’équipe Swap’Îles.
        </p>
    </div>
</section>
@endsection
