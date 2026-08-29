<x-filament-panels::page>
    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        <x-filament::section>
            <x-slot name="heading">🏆 Top des dressings (aperçu des destinataires)</x-slot>
            <x-slot name="description">
                Ces vendeurs recevront l’e-mail de félicitations « Bravo, tu es dans le Top {{ $top->count() }} ! »
                avec le lien vers la page publique du classement. Clique sur « Envoyer les félicitations » en haut à droite.
            </x-slot>

            @if($top->isEmpty())
                <p style="color:#6b7280;">Aucun dressing classé pour le moment (il faut un minimum d’engagement).</p>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                        <thead>
                            <tr style="text-align:left;color:#6b7280;border-bottom:1px solid #e5e7eb;">
                                <th style="padding:.5rem;">#</th>
                                <th style="padding:.5rem;">Vendeur</th>
                                <th style="padding:.5rem;">E-mail</th>
                                <th style="padding:.5rem;">❤️</th>
                                <th style="padding:.5rem;">👁</th>
                                <th style="padding:.5rem;">🛒</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($top as $row)
                                <tr style="border-bottom:1px solid #f3f4f6;">
                                    <td style="padding:.5rem;font-weight:700;">{{ $row->rank }}</td>
                                    <td style="padding:.5rem;font-weight:600;">{{ $row->user->name }}</td>
                                    <td style="padding:.5rem;color:#6b7280;">{{ $row->user->email }}</td>
                                    <td style="padding:.5rem;">{{ $row->favorites }}</td>
                                    <td style="padding:.5rem;">{{ $row->views }}</td>
                                    <td style="padding:.5rem;">{{ $row->sales }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">⚠️ À savoir avant d’envoyer</x-slot>
            <ul style="margin:0;padding-left:1.1rem;color:#4b5563;line-height:1.7;">
                <li>Le <strong>DKIM du domaine n’est pas encore vérifié</strong> : l’e-mail peut arriver en spam. Idéalement, configure le DNS d’abord.</li>
                <li>L’envoi part vers <strong>tes meilleurs vendeurs</strong> — une liste précieuse. Un message de félicitations est bien accueilli, mais reste prudent sur la fréquence.</li>
                <li>Le classement (donc les destinataires et leur rang) est calculé <strong>au moment de l’envoi</strong>.</li>
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
