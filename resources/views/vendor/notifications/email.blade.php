{{-- Modèle des e-mails de notification, en français et signé Swap'Îles
     (celui de Laravel finissait par « Regards, Laravel » et un pied en anglais). --}}
<x-mail::message>
{{-- Salutation d'ouverture --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# Oups !
@else
# Bonjour !
@endif
@endif

{{-- Texte --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Bouton --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Signature --}}
@if (! empty($salutation))
{{ $salutation }}
@else
À très vite,<br>
L’équipe Swap’Îles 🌴
@endif

{{-- Lien de secours --}}
@isset($actionText)
<x-slot:subcopy>
Le bouton « {{ $actionText }} » ne s’ouvre pas ? Copiez ce lien dans votre navigateur :
<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
