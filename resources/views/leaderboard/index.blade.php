@extends('layouts.app')

@section('title', 'Meilleurs dressings — Swap\'Îles')
@section('meta_description', 'Le classement des dressings les plus populaires de Swap\'Îles : les vendeurs les plus suivis, favoris et actifs des Outre-mer.')

@php
    // Rendu d'un avatar (photo ou initiale).
    $avatar = function ($user, $size = 'h-14 w-14') {
        $classes = $size . ' rounded-full object-cover bg-teal-100 text-teal-800 grid place-items-center font-extrabold';
        if ($user?->avatar) {
            return '<img src="' . e($user->avatar) . '" alt="' . e($user->name) . '" class="' . $classes . '">';
        }
        $initial = mb_strtoupper(mb_substr($user?->name ?? '?', 0, 1));
        return '<span class="' . $classes . '">' . e($initial) . '</span>';
    };
@endphp

@section('content')

{{-- En-tête --}}
<section class="relative overflow-hidden bg-teal-900">
    <div class="absolute inset-0 bg-gradient-to-br from-teal-950 via-teal-800 to-emerald-600"></div>
    <div class="relative max-w-4xl mx-auto px-4 py-14 sm:py-16 text-center text-white">
        <h1 class="text-3xl sm:text-4xl font-extrabold">🏆 Les meilleurs dressings</h1>
        <p class="mt-3 mx-auto max-w-xl text-teal-50/90">
            Le classement des vendeurs les plus populaires de Swap'Îles : les dressings les plus vus, aimés et actifs de nos îles.
        </p>
    </div>
</section>

<section class="bg-gray-50 min-h-[40vh]">
    <div class="max-w-4xl mx-auto px-4 py-10 sm:py-14">

        {{-- Bandeau « ton rang » --}}
        @auth
            @if($myRank)
                <div class="mb-8 rounded-2xl bg-teal-50 border border-teal-100 p-4 text-center text-teal-900">
                    <p class="font-extrabold">Ton dressing est classé #{{ $myRank }} 🎉</p>
                    <p class="mt-1 text-sm">Publie, réponds vite et anime ton dressing pour grimper encore.</p>
                </div>
            @else
                <div class="mb-8 rounded-2xl bg-white border border-gray-100 p-4 text-center text-gray-700">
                    <p class="font-extrabold">Ton dressing n'est pas encore classé</p>
                    <p class="mt-1 text-sm">Publie des annonces et partage-les : les vues, favoris et messages te feront apparaître ici.</p>
                </div>
            @endif
        @endauth

        @if($top->isEmpty())
            <div class="rounded-3xl border border-gray-100 bg-white p-10 text-center">
                <p class="text-5xl">🏝️</p>
                <p class="mt-4 text-lg font-extrabold text-gray-900">Le classement arrive bientôt</p>
                <p class="mt-1 text-gray-500">Dès que les dressings prennent vie (vues, favoris, ventes), le Top s'affiche ici.</p>
                <a href="{{ route('search') }}" class="mt-6 inline-block rounded-2xl bg-teal-700 px-6 py-3 font-extrabold text-white hover:bg-teal-800 transition">Explorer les annonces</a>
            </div>
        @else
            {{-- Podium (top 3) --}}
            @php $podium = $top->take(3); $rest = $top->slice(3); @endphp
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach($podium as $row)
                    @php
                        $medal = ['🥇', '🥈', '🥉'][$row->rank - 1] ?? '🏅';
                        $ring = $row->rank === 1 ? 'border-amber-300 ring-2 ring-amber-200' : 'border-gray-100';
                        $order = $row->rank === 1 ? 'sm:order-2 sm:-mt-4' : ($row->rank === 2 ? 'sm:order-1' : 'sm:order-3');
                    @endphp
                    <a href="{{ route('profiles.show', $row->user) }}"
                       class="{{ $order }} block rounded-3xl border {{ $ring }} bg-white p-6 text-center shadow-sm hover:shadow-md transition">
                        <div class="text-3xl">{{ $medal }}</div>
                        <div class="mt-3 flex justify-center">{!! $avatar($row->user, 'h-16 w-16') !!}</div>
                        <p class="mt-3 font-extrabold text-gray-900 truncate">{{ $row->user->name }}</p>
                        @if($row->user->territoire)
                            <p class="text-xs text-gray-400">{{ \App\Support\Territoires::display($row->user->territoire) }}</p>
                        @endif
                        <div class="mt-3 flex flex-wrap justify-center gap-1.5 text-xs">
                            <span class="rounded-full bg-rose-50 px-2 py-0.5 font-semibold text-rose-700">❤️ {{ $row->favorites }}</span>
                            <span class="rounded-full bg-blue-50 px-2 py-0.5 font-semibold text-blue-700">👁 {{ $row->views }}</span>
                            @if($row->sales > 0)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700">🛒 {{ $row->sales }}</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Reste du classement (4+) --}}
            @if($rest->isNotEmpty())
                <div class="mt-6 divide-y divide-gray-100 rounded-3xl border border-gray-100 bg-white">
                    @foreach($rest as $row)
                        <a href="{{ route('profiles.show', $row->user) }}" class="flex items-center gap-4 p-4 hover:bg-gray-50 transition">
                            <span class="w-7 shrink-0 text-center font-extrabold text-gray-400">{{ $row->rank }}</span>
                            {!! $avatar($row->user, 'h-11 w-11') !!}
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-gray-900 truncate">{{ $row->user->name }}</p>
                                @if($row->user->territoire)
                                    <p class="text-xs text-gray-400">{{ \App\Support\Territoires::display($row->user->territoire) }}</p>
                                @endif
                            </div>
                            <div class="hidden sm:flex flex-wrap justify-end gap-1.5 text-xs">
                                <span class="rounded-full bg-rose-50 px-2 py-0.5 font-semibold text-rose-700">❤️ {{ $row->favorites }}</span>
                                <span class="rounded-full bg-blue-50 px-2 py-0.5 font-semibold text-blue-700">👁 {{ $row->views }}</span>
                                @if($row->sales > 0)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700">🛒 {{ $row->sales }}</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            <p class="mt-6 text-center text-xs text-gray-400">
                Classement mis à jour en continu selon les vues, favoris, messages, ventes et avis.
            </p>
        @endif
    </div>
</section>

@endsection
