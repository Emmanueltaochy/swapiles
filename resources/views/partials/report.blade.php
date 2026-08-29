{{--
    Bouton « Signaler » réutilisable (annonce ou membre).
    Paramètres :
      - $action : URL du formulaire (route reports.listing / reports.user)
      - $label  : intitulé de ce qu'on signale (ex : « cette annonce », « ce membre »)
      - $align  : 'left' | 'right' (alignement du panneau déroulant), défaut 'right'
    Dropdown sans JS grâce à <details>.
--}}
@auth
    @php $align = $align ?? 'right'; @endphp
    <details class="group relative inline-block text-left">
        <summary class="flex cursor-pointer list-none items-center gap-1 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
            <span aria-hidden="true">🚩</span> Signaler
        </summary>
        <div class="absolute {{ $align === 'left' ? 'left-0' : 'right-0' }} z-30 mt-2 w-72 rounded-2xl border border-gray-100 bg-white p-4 shadow-xl">
            @if(session('report_sent'))
                <div class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">
                    ✅ Merci, votre signalement a bien été transmis à notre équipe.
                </div>
            @else
                <p class="mb-2 text-sm font-bold text-gray-900">Signaler {{ $label }}</p>
                <form method="POST" action="{{ $action }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="sr-only" for="report-reason">Motif</label>
                        <select id="report-reason" name="reason" required
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-900 outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                            <option value="">Choisir un motif…</option>
                            @foreach(\App\Models\Report::REASONS as $key => $reasonLabel)
                                <option value="{{ $key }}">{{ $reasonLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="sr-only" for="report-details">Détails (facultatif)</label>
                        <textarea id="report-details" name="details" rows="2" maxlength="1000"
                                  placeholder="Détails (facultatif)…"
                                  class="w-full resize-none rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-900 outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100"></textarea>
                    </div>
                    @error('reason')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <button class="w-full rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700">
                        Envoyer le signalement
                    </button>
                </form>
            @endif
        </div>
    </details>
@endauth
