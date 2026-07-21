@extends('layouts.app')

@section('title', 'Messages — Swap Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-6">
            <h1 class="text-3xl font-extrabold text-gray-900">Messages</h1>
            <p class="text-gray-500 mt-2">Retrouvez vos conversations avec les membres Swap Îles.</p>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            @forelse($conversations as $conversation)
                @php
                    $other = $conversation->sender_id === auth()->id() ? $conversation->receiver : $conversation->sender;
                    $isUnread = $conversation->receiver_id === auth()->id() && is_null($conversation->read_at);
                    $url = $conversation->listing
                        ? route('account.messages.show', ['listing' => $conversation->listing, 'user' => $other])
                        : route('account.messages.show.general', ['user' => $other]);
                @endphp

                <a href="{{ $url }}" class="block p-5 border-b border-gray-100 hover:bg-gray-50 {{ $isUnread ? 'bg-teal-50/70' : '' }}">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-gray-100 overflow-hidden shrink-0 flex items-center justify-center">
                            @if($conversation->listing?->images?->first())
                                <img src="{{ $conversation->listing->images->first()->url }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-2xl">💬</span>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-extrabold text-gray-900 truncate">
                                    {{ $other->name ?? 'Utilisateur' }}
                                </p>

                                @if($isUnread)
                                    <span class="shrink-0 w-3 h-3 rounded-full bg-red-600"></span>
                                @endif
                            </div>

                            <p class="text-sm font-bold text-gray-600 truncate mt-1">
                                {{ $conversation->listing?->title ?? 'Conversation directe' }}
                            </p>

                            <p class="text-sm text-gray-500 truncate mt-1">
                                {{ $conversation->body }}
                            </p>
                        </div>
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
