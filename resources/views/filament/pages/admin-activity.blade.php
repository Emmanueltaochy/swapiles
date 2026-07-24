<x-filament-panels::page>
    {{-- Onglets --}}
    <div style="display:flex;gap:.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
        <a href="{{ \App\Filament\Pages\AdminActivity::getUrl(['tab' => 'activity']) }}"
           style="padding:.55rem 1.1rem;border-radius:.7rem;font-weight:800;text-decoration:none;
                  {{ $tab === 'activity' ? 'background:#0d9488;color:#fff;' : 'background:rgba(148,163,184,.15);color:inherit;' }}">
            ⚡ Activité
        </a>
        <a href="{{ \App\Filament\Pages\AdminActivity::getUrl(['tab' => 'emails']) }}"
           style="padding:.55rem 1.1rem;border-radius:.7rem;font-weight:800;text-decoration:none;
                  {{ $tab === 'emails' ? 'background:#0d9488;color:#fff;' : 'background:rgba(148,163,184,.15);color:inherit;' }}">
            ✉️ Emails envoyés
        </a>
    </div>

    @if($tab === 'activity')
        <x-filament::section>
            <x-slot name="heading">Dernières actions sur le site</x-slot>
            <x-slot name="description">Inscriptions, annonces, transactions, demandes de livraison, favoris, avis, messages — les 120 plus récentes.</x-slot>

            @forelse($activities as $item)
                <div style="display:flex;gap:.8rem;align-items:flex-start;padding:.6rem 0;border-bottom:1px solid rgba(148,163,184,.15);">
                    <span style="font-size:1.15rem;line-height:1.4;">{{ $item['icon'] }}</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:.9rem;">
                            @if($item['url'])
                                <a href="{{ $item['url'] }}" style="color:#0d9488;font-weight:600;text-decoration:none;">{{ $item['text'] }}</a>
                            @else
                                <span>{{ $item['text'] }}</span>
                            @endif
                        </div>
                        <div style="font-size:.75rem;opacity:.55;margin-top:.15rem;">
                            {{ optional($item['at'])->format('d/m/Y H:i') }} · {{ optional($item['at'])->diffForHumans() }}
                        </div>
                    </div>
                </div>
            @empty
                <p style="opacity:.6;">Aucune activité récente.</p>
            @endforelse
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Emails envoyés aux utilisateurs</x-slot>
            <x-slot name="description">Chaque e-mail transactionnel envoyé (destinataire, objet, date) — les 300 plus récents.</x-slot>

            @if(! $emailsTable)
                <p style="opacity:.6;">Le journal des e-mails sera disponible après le prochain déploiement.</p>
            @elseif($emails->isEmpty())
                <p style="opacity:.6;">Aucun e-mail enregistré pour le moment (les envois sont journalisés à partir de maintenant).</p>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                        <thead>
                            <tr style="text-align:left;border-bottom:2px solid rgba(148,163,184,.25);">
                                <th style="padding:.5rem .6rem;">Quand</th>
                                <th style="padding:.5rem .6rem;">Destinataire</th>
                                <th style="padding:.5rem .6rem;">Objet</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($emails as $mail)
                                <tr style="border-bottom:1px solid rgba(148,163,184,.15);">
                                    <td style="padding:.5rem .6rem;white-space:nowrap;opacity:.7;">{{ optional($mail->created_at)->format('d/m/Y H:i') }}</td>
                                    <td style="padding:.5rem .6rem;">
                                        <div style="font-weight:600;">{{ $mail->to_name ?: '—' }}</div>
                                        <div style="opacity:.6;font-size:.78rem;">{{ $mail->to_email }}</div>
                                    </td>
                                    <td style="padding:.5rem .6rem;">{{ $mail->subject ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
