@extends('layouts.app')

@section('title', 'Messages — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-6">
            <h1 class="text-3xl font-extrabold text-gray-900">Messages</h1>
            <p class="text-gray-500 mt-2">Retrouvez vos conversations avec les acheteurs et vendeurs.</p>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            @forelse($conversations as $message)
                @php
                    $other = $message->sender_id === auth()->id() ? $message->receiver : $message->sender;
                    $listing = $message->listing;
                    $unread = $message->receiver_id === auth()->id() && is_null($message->read_at);
                @endphp

                <a href="{{ route('account.messages.show', ['listing' => $listing, 'user' => $other]) }}"
                   class="flex gap-4 p-4 border-b border-gray-100 hover:bg-gray-50 transition">

                    <div class="w-20 h-20 rounded-2xl bg-gray-100 overflow-hidden shrink-0">
                        @if($listing && $listing->images->first())
                            <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300 text-3xl">📦</div>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-extrabold text-gray-900 truncate">
                                {{ $other->name ?? 'Utilisateur' }}
                            </p>

                            @if($unread)
                                <span class="bg-teal-700 text-white text-xs font-bold px-2 py-1 rounded-full">
                                    Nouveau
                                </span>
                            @endif
                        </div>

                        <p class="text-sm text-gray-500 truncate mt-1">
                            {{ $listing->title ?? 'Annonce supprimée' }}
                        </p>

                        <p class="text-sm text-gray-700 truncate mt-2">
                            {{ $message->body }}
                        </p>
                    </div>
                </a>
            @empty
                <div class="p-10 text-center">
                    <div class="text-5xl mb-3">💬</div>
                    <h2 class="text-xl font-bold text-gray-900">Aucun message</h2>
                    <p class="text-gray-500 mt-2">Vos conversations apparaîtront ici.</p>
                </div>
            @endforelse
        </div>

    </div>
</section>
@endsection
