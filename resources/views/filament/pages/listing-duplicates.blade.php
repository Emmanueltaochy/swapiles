<x-filament-panels::page>
    <div class="space-y-4">

        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500">Annonces en trop</p>
            <p class="mt-1 text-3xl font-bold">{{ $enTrop }}</p>
            <p class="mt-2 text-sm text-gray-500">
                Même vendeur, même titre, même prix. La plus ancienne est conservée :
                c’est elle qui porte les vues, les favoris et les messages.
                Une copie déjà vendue n’est jamais supprimée.
            </p>
        </div>

        <div class="space-y-3">
            @forelse($groupes as $g)
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-base font-bold text-gray-900 dark:text-gray-100 break-words">
                        {{ $g['keep']->title }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $g['keep']->user?->name ?? 'Vendeur inconnu' }}
                        · {{ number_format($g['keep']->price, 0, ',', ' ') }} €
                    </p>

                    <div class="mt-3 space-y-1 text-sm">
                        <p class="text-gray-600 dark:text-gray-300">
                            ✅ Conservée : #{{ $g['keep']->id }}
                            <span class="text-xs text-gray-400">
                                ({{ $g['keep']->created_at?->format('d/m/Y H:i') }})
                            </span>
                        </p>
                        @foreach($g['copies'] as $copie)
                            <p class="text-gray-500">
                                🗑️ Copie : #{{ $copie->id }}
                                <span class="text-xs text-gray-400">
                                    ({{ $copie->created_at?->format('d/m/Y H:i') }})
                                </span>
                            </p>
                        @endforeach
                    </div>

                    <button
                        type="button"
                        wire:click="supprimerCopies({{ $g['keep']->id }})"
                        wire:confirm="Supprimer {{ $g['copies']->count() }} copie(s) et garder l’annonce d’origine ?"
                        class="mt-3 rounded-xl bg-danger-600 px-4 py-2 text-sm font-semibold text-white hover:bg-danger-700"
                    >
                        Supprimer les {{ $g['copies']->count() }} copie(s)
                    </button>
                </div>
            @empty
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-6 text-center text-gray-500">
                    Aucune annonce en double. 🎉
                </div>
            @endforelse
        </div>

    </div>
</x-filament-panels::page>
