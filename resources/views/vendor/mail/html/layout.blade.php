<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
<div style="font-size:28px;font-weight:900;color:#0f766e;letter-spacing:-0.5px;">
    Swap’Îles
</div>
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{{ $slot }}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
<strong>L’équipe Swap’Îles</strong><br>
La marketplace seconde main des îles<br>
<a href="{{ config('app.url') }}" style="color:#0f766e;">{{ config('app.url') }}</a><br><br>
© {{ date('Y') }} Swap’Îles. Tous droits réservés.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
