@extends('layouts.app')

@section('title', 'Conversation — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <div class="mb-4">
            <a href="{{ route('account.messages.index') }}" class="text-sm font-bold text-teal-700 hover:text-teal-900">
                ← Retour aux messages
            </a>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">

            <div class="p-4 border-b border-gray-100 flex gap-4 items-center">
                <a href="{{ route('listings.show', $listing) }}" class="w-16 h-16 rounded-2xl bg-gray-100 overflow-hidden shrink-0">
                    @if($listing->images->first())
                        <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-300 text-2xl">📦</div>
                    @endif
                </a>

                <div class="flex-1 min-w-0">
                    <p class="font-extrabold text-gray-900 truncate">{{ $listing->title }}</p>
                    <p class="text-sm text-gray-500">
                        Conversation avec <span class="font-bold text-gray-700">{{ $user->name }}</span>
                    </p>
                </div>
            </div>

            <div class="p-4 sm:p-6 space-y-4 bg-gray-50 min-h-[420px]">
                @forelse($messages as $message)
                    @php $mine = $message->sender_id === auth()->id(); @endphp

                    <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[80%] rounded-3xl px-4 py-3 text-sm
                            {{ $mine ? 'bg-teal-700 text-white rounded-br-md' : 'bg-white text-gray-800 border border-gray-100 rounded-bl-md' }}">
                            <p class="whitespace-pre-line">{{ $message->body }}</p>
                            <p class="mt-2 text-[11px] {{ $mine ? 'text-white/70' : 'text-gray-400' }}">
                                {{ $message->created_at->format('d/m/Y H:i') }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-16">
                        <div class="text-5xl mb-3">💬</div>
                        <h2 class="text-xl font-bold text-gray-900">Démarrer la conversation</h2>
                        <p class="text-gray-500 mt-2">Envoyez un premier message concernant cette annonce.</p>
                    </div>
                @endforelse
            </div>

            <form method="POST" action="{{ route('account.messages.store', ['listing' => $listing, 'user' => $user]) }}" class="p-4 bg-white border-t border-gray-100">
                @csrf

                <div class="flex gap-3">
                    <textarea
                        name="body"
                        rows="2"
                        required
                        placeholder="Écrire un message..."
                        class="flex-1 rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600 resize-none"
                    ></textarea>

                    <button class="bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-5 py-3 transition">
                        Envoyer
                    </button>
                </div>

                @error('body')
                    <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
                @enderror
            </form>

        </div>

    </div>
</section>
@endsection
