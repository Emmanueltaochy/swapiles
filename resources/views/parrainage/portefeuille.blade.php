@extends('layouts.app')

@section('title', 'Mon portefeuille — Swap’Îles')

@section('content')
@php $euros = fn (int $c) => number_format($c / 100, 2, ',', ' ') . ' €'; @endphp
<section class="mx-auto max-w-2xl px-4 py-6 sm:py-10">
    <a href="{{ route('parrainage.index') }}" class="text-sm font-semibold text-teal-700">← Inviter des amis</a>
    <h1 class="mt-2 text-2xl font-extrabold text-gray-900 sm:text-3xl">💚 Mon portefeuille</h1>

    <div class="mt-5 rounded-2xl bg-gradient-to-br from-teal-700 to-emerald-600 p-6 text-white shadow-lg">
        <p class="text-sm font-semibold text-white/80">Crédit disponible</p>
        <p class="mt-1 text-4xl font-extrabold">{{ $euros($solde) }}</p>
        <p class="mt-2 text-sm text-white/85">Déduit automatiquement au moment de payer un achat (il reste au minimum 0,50 € à régler par carte).</p>
    </div>

    @if($lots->isNotEmpty())
        <div class="mt-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-gray-900">Dates d’expiration</h2>
            <ul class="mt-2 divide-y divide-gray-100 text-sm">
                @foreach($lots as $lot)
                    <li class="flex justify-between py-2">
                        <span class="text-gray-700">{{ $euros($lot->restant_centimes) }}</span>
                        <span class="{{ $lot->expire_at->lt(now()->addDays(8)) ? 'font-bold text-amber-700' : 'text-gray-500' }}">expire le {{ $lot->expire_at->format('d/m/Y') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-gray-900">Historique</h2>
        @if($mouvements->isEmpty())
            <p class="mt-3 rounded-xl bg-gray-50 p-4 text-center text-sm text-gray-500">Rien pour l’instant. Invite tes amis pour gagner du crédit 🌴</p>
        @else
            <ul class="mt-2 divide-y divide-gray-100 text-sm">
                @foreach($mouvements as $m)
                    <li class="flex items-start justify-between gap-3 py-2.5">
                        <span>
                            <span class="block font-semibold text-gray-900">{{ \App\Models\PortefeuilleMouvement::LIBELLES[$m->type] ?? $m->type }}</span>
                            <span class="block text-xs text-gray-500">
                                {{ $m->created_at->format('d/m/Y') }}
                                @if($m->note) · {{ $m->note }}@endif
                                @if($m->type === 'utilisation' && $m->transaction?->listing) · {{ $m->transaction->listing->title }}@endif
                            </span>
                        </span>
                        <span class="shrink-0 font-bold {{ $m->montant_centimes >= 0 ? 'text-emerald-700' : 'text-gray-700' }}">{{ $m->montant_centimes >= 0 ? '+' : '−' }}{{ $euros(abs($m->montant_centimes)) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <p class="mt-6 text-center text-xs text-gray-500">Le crédit n’est ni remboursable en argent ni transférable. <a href="{{ route('parrainage.conditions') }}" class="underline">Conditions du parrainage</a></p>
</section>
@endsection
