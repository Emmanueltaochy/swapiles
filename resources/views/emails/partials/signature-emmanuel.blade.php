{{-- Signature d'Emmanuel pour les réponses envoyées depuis contact@. --}}
<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:22px;border-top:1px solid #e5e7eb;padding-top:14px;font-family:Arial,sans-serif;">
    <tr>
        <td style="padding-right:14px;vertical-align:top;">
            <img src="{{ url('/images/app-icon.png') }}" alt="Swap’Îles" width="52" height="52" style="display:block;border-radius:12px;">
        </td>
        <td style="vertical-align:top;font-size:13px;line-height:1.5;color:#374151;">
            <div style="font-size:15px;font-weight:bold;color:#111827;">{{ config('agent.signature.nom', 'Emmanuel Taochy') }}</div>
            <div>{{ config('agent.signature.fonction', 'Fondateur de Swap’Îles') }}</div>
            <div style="color:#0f766e;font-weight:bold;">La seconde main des Outre-mer : acheter, vendre, donner, échanger</div>
            @if(filled(config('partenaires.contact.telephone')))
                <div>📞 {{ config('partenaires.contact.telephone') }}</div>
            @endif
            <div>✉️ <a href="mailto:contact@swapiles.com" style="color:#0f766e;">contact@swapiles.com</a> · 🌐 <a href="https://swapiles.com" style="color:#0f766e;">swapiles.com</a></div>
            <div style="font-size:12px;color:#6b7280;margin-top:2px;">Application disponible sur l’App Store et Google Play</div>
        </td>
    </tr>
</table>
