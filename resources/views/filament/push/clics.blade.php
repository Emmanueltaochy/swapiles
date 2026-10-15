<div style="font-size:14px">
    <p style="margin-bottom:12px;color:#6b7280">
        {{ $envoi->clics }} clic(s)
        @if($envoi->estDiffusion() && $envoi->appareils_ok) sur {{ $envoi->appareils_ok }} appareil(s) atteint(s) @endif
        · envoyée le {{ $envoi->created_at->format('d/m/Y à H:i') }}
    </p>
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="text-align:left;color:#6b7280;font-size:12px;text-transform:uppercase">
                <th style="padding:6px 4px">Qui</th>
                <th style="padding:6px 4px">Téléphone</th>
                <th style="padding:6px 4px">Quand</th>
            </tr>
        </thead>
        <tbody>
            @foreach($clics as $c)
                <tr style="border-top:1px solid rgba(127,127,127,.15)">
                    <td style="padding:8px 4px">
                        @if($c->user)
                            <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $c->user_id]) }}" style="font-weight:600;color:#0d9488">{{ $c->user->name }}</a>
                            <div style="font-size:12px;color:#6b7280">{{ $c->user->email }}</div>
                        @else
                            <span style="color:#6b7280">Visiteur non connecté</span>
                        @endif
                    </td>
                    <td style="padding:8px 4px">{{ ['ios' => 'iPhone', 'android' => 'Android', 'web' => 'Navigateur'][$c->plateforme] ?? '—' }}</td>
                    <td style="padding:8px 4px">{{ $c->created_at?->format('d/m H:i') }}
                        <div style="font-size:12px;color:#6b7280">{{ $c->created_at ? $envoi->created_at->diffForHumans($c->created_at, true) . ' après' : '' }}</div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
