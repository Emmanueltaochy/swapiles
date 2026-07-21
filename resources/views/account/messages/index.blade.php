@extends('layouts.app')

@section('title', 'Messages — Swap Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-6 sm:py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-5">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Messages</h1>
            <p class="mt-1 text-gray-500">Tes conversations avec les membres Swap'Îles.</p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            @forelse($conversations as $conversation)
                @php
                    $other = $conversation->sender_id === auth()->id() ? $conversation->receiver : $conversation->sender;
                    $isUnread = $conversation->receiver_id === auth()->id() && is_null($conversation->read_at);
                    $url = $conversation->listing
                        ? route('account.messages.show', ['listing' => $conversation->listing, 'user' => $other])
                        : route('account.messages.show.general', ['user' => $other]);
                @endphp

                <a href="{{ $url }}" class="flex items-center gap-4 border-b border-gray-100 p-4 transition hover:bg-gray-50 {{ $isUnread ? 'bg-teal-50/60' : '' }}">
                    <div class="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-xl bg-gray-100">
                        @if($conversation->listing?->images?->first())
                            <img src="{{ $conversation->listing->images->first()->url }}" alt="" class="h-full w-full object-cover">
                        @else
                            <span class="text-2xl" aria-hidden="true">💬</span>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-3">
                            <p class="truncate font-semibold text-gray-900">{{ $other->name ?? 'Utilisateur' }}</p>
                            @if($isUnread)
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-red-500" aria-label="Non lu"></span>
                            @endif
                        </div>
                        <p class="mt-0.5 truncate text-sm font-medium text-gray-600">{{ $conversation->listing?->title ?? 'Conversation directe' }}</p>
                        <p class="mt-0.5 truncate text-sm text-gray-400">{{ $conversation->body }}</p>
                    </div>
                </a>
            @empty
                <div class="p-10 text-center">
                    <div class="text-5xl" aria-hidden="true">💬</div>
                    <h2 class="mt-3 text-lg font-bold text-gray-900">Aucun message</h2>
                    <p class="mt-1 text-gray-500">Tes conversations apparaîtront ici.</p>
                </div>
            @endforelse
        </div>

    </div>
</section>
@endsection
