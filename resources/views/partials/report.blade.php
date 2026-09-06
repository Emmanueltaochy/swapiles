{{--
    Bouton « Signaler » + fenêtre de signalement (annonce ou membre).

    Déroulé, calqué sur ce que les membres connaissent ailleurs :
      1. choix du motif (+ détails facultatifs) → « Signaler »
      2. « Contenu signalé » → proposition de bloquer la personne dans la foulée

    Paramètres :
      - $action : URL du formulaire (route reports.listing / reports.user)
      - $label  : intitulé de ce qu'on signale (ex : « cette annonce », « ce membre »)
      - $align  : conservé pour compatibilité, sans effet (la fenêtre est centrée)
--}}
@auth
    @php $uid = 'report-' . \Illuminate\Support\Str::random(6); @endphp

    <button type="button"
            data-report-open="{{ $uid }}"
            class="flex items-center gap-1 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
        <span aria-hidden="true">🚩</span> Signaler
    </button>

    <div id="{{ $uid }}" data-report-modal hidden
         class="fixed inset-0 z-[10000] items-center justify-center bg-black/60 p-4"
         role="dialog" aria-modal="true" aria-labelledby="{{ $uid }}-titre">

        <div class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl" data-report-card>

            {{-- Étape 1 : motif --}}
            <div data-report-step="form">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <p id="{{ $uid }}-titre" class="text-base font-bold text-gray-900">Signaler {{ $label }}</p>
                    <button type="button" data-report-close aria-label="Fermer"
                            class="grid h-8 w-8 place-items-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">✕</button>
                </div>

                <form data-report-form action="{{ $action }}" method="POST" class="space-y-4 px-5 py-4">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-800" for="{{ $uid }}-reason">
                            Pourquoi signalez-vous {{ $label }} ?
                        </label>
                        <select id="{{ $uid }}-reason" name="reason" required
                                class="w-full rounded-xl border border-gray-200 px-3 py-3 text-sm text-gray-900 outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                            <option value="">Choisir un motif…</option>
                            @foreach(\App\Models\Report::REASONS as $key => $reasonLabel)
                                <option value="{{ $key }}">{{ $reasonLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="sr-only" for="{{ $uid }}-details">Détails (facultatif)</label>
                        <textarea id="{{ $uid }}-details" name="details" rows="3" maxlength="1000"
                                  placeholder="Ajoutez un détail si besoin (facultatif)…"
                                  class="w-full resize-none rounded-xl border border-gray-200 px-3 py-3 text-sm text-gray-900 outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100"></textarea>
                    </div>

                    <p data-report-error hidden class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                    <button type="submit" data-report-submit
                            class="w-full rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60">
                        Signaler
                    </button>

                    <p class="text-center text-xs text-gray-400">
                        Votre signalement est anonyme pour la personne concernée.
                    </p>
                </form>
            </div>

            {{-- Étape 2 : confirmation + proposition de blocage --}}
            <div data-report-step="done" hidden class="px-5 py-6 text-center">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-2xl" aria-hidden="true">✅</div>
                <p class="mt-3 text-base font-bold text-gray-900">Contenu signalé</p>
                <p class="mt-1 text-sm text-gray-500">
                    Merci. Notre équipe va examiner ce signalement.
                </p>

                <div data-report-block hidden class="mt-5 border-t border-gray-100 pt-5">
                    <p class="text-sm text-gray-700">
                        Souhaitez-vous aussi bloquer <strong data-report-block-name></strong> ?
                    </p>
                    <p class="mt-1 text-xs text-gray-400">
                        Vous ne recevrez plus ses messages. Vous pourrez le débloquer à tout moment.
                    </p>
                    <button type="button" data-report-block-btn
                            class="mt-3 w-full rounded-xl bg-gray-900 px-4 py-3 text-sm font-bold text-white transition hover:bg-black disabled:opacity-60">
                        Bloquer
                    </button>
                </div>

                <p data-report-block-done hidden class="mt-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-800"></p>

                <button type="button" data-report-close
                        class="mt-4 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    Fermer
                </button>
            </div>

        </div>
    </div>
@endauth
