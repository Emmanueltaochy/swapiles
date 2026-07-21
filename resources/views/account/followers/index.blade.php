@extends('layouts.app')

@section('title', 'Mes abonnés — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-sm font-black uppercase tracking-wide text-teal-700">Communauté</p>
                <h1 class="text-3xl sm:text-4xl font-black text-gray-950">Mes abonnés</h1>
                <p class="text-gray-500 mt-2">Les membres qui suivent votre dressing.</p>
            </div>

            <a href="{{ route('account.dashboard') }}"
               class="bg-white border border-gray-100 shadow-sm rounded-2xl px-5 py-3 font-black text-gray-700">
                ← Mon compte
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($followers as $follower)
                <a href="{{ route('profiles.show', $follower) }}"
                   class="bg-white rounded-[32px] border border-gray-100 shadow-sm hover:shadow-xl transition p-5">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-3xl bg-teal-100 flex items-center justify-center overflow-hidden text-2xl font-black text-teal-800">
                            @if($follower->avatar)
                                <img src="{{ $follower->avatar }}" alt="{{ $follower->name }}" class="w-full h-full object-cover">
                            @else
                                {{ strtoupper(substr($follower->name, 0, 1)) }}
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="font-black text-gray-950 truncate">{{ $follower->name }}</p>
                            <p class="text-sm text-gray-500 mt-1">
                                📅 Suit depuis {{ $follower->pivot->created_at?->format('d/m/Y') }}
                            </p>
                            <p class="text-xs text-teal-700 font-black mt-1">Voir le profil →</p>
                        </div>
                    </div>
                </a>
            @empty
                <div class="col-span-full bg-white border border-dashed border-gray-200 rounded-[32px] p-10 text-center">
                    <div class="text-5xl mb-3">👥</div>
                    <h2 class="text-xl font-black text-gray-950">Aucun abonné pour le moment</h2>
                    <p class="text-gray-500 mt-2">Vos abonnés apparaîtront ici quand des membres suivront votre profil.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-8">
            {{ $followers->links() }}
        </div>

    </div>
</section>
@endsection
