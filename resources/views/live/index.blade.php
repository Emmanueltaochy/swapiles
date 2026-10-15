@extends('layouts.app')

@section('title', 'Lives — Swap\'Îles')

@section('content')
<section class="max-w-3xl mx-auto px-4 py-6 sm:py-10">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900">🔴 Ventes en direct</h1>
            @unless(config('live.ouvert_a_tous'))
                <p class="mt-1 text-xs font-semibold text-amber-700">🧪 Mode test : visible seulement par vous.</p>
            @endunless
        </div>
        @auth
            <a href="{{ route('live.create') }}" class="shrink-0 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-teal-800">+ Créer un live</a>
        @endauth
    </div>

    <h2 class="mt-8 mb-3 text-lg font-extrabold text-gray-900">En ce moment</h2>
    @forelse($enDirect as $live)
        <a href="{{ route('live.show', $live) }}" class="mb-3 flex items-center gap-3 rounded-2xl border border-red-100 bg-red-50 p-4">
            <span class="rounded-full bg-red-600 px-2.5 py-1 text-xs font-extrabold text-white">EN DIRECT</span>
            <span class="min-w-0">
                <span class="block truncate font-bold text-gray-900">{{ $live->titre }}</span>
                <span class="block text-sm text-gray-600">{{ $live->vendeur->name ?? '' }}</span>
            </span>
        </a>
    @empty
        <p class="text-sm text-gray-500">Aucun live en cours.</p>
    @endforelse

    <h2 class="mt-8 mb-3 text-lg font-extrabold text-gray-900">À venir</h2>
    @forelse($programmes as $live)
        <a href="{{ route('live.show', $live) }}" class="mb-3 block rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <span class="block font-bold text-gray-900">{{ $live->titre }}</span>
            <span class="block text-sm text-gray-600">
                {{ $live->vendeur->name ?? '' }}
                @if($live->debut_prevu_at) · {{ ucfirst($live->debut_prevu_at->locale('fr')->translatedFormat('l j F à H\hi')) }}@endif
            </span>
        </a>
    @empty
        <p class="text-sm text-gray-500">Aucun live programmé.</p>
    @endforelse

    @if($mesLives->isNotEmpty())
        <h2 class="mt-8 mb-3 text-lg font-extrabold text-gray-900">Mes lives</h2>
        @foreach($mesLives as $live)
            <a href="{{ route('live.show', $live) }}" class="mb-2 flex justify-between gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3 text-sm">
                <span class="truncate font-semibold text-gray-800">{{ $live->titre }}</span>
                <span class="shrink-0 text-gray-500">{{ \App\Models\LiveSession::STATUTS[$live->statut] }}</span>
            </a>
        @endforeach
    @endif
</section>
@endsection
