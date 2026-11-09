{!! $texte !!}

--
{{ config('agent.signature.nom', 'Emmanuel Taochy') }}
{{ config('agent.signature.fonction', 'Fondateur de Swap’Îles') }}
@if(filled(config('partenaires.contact.telephone')))
Tél. {{ config('partenaires.contact.telephone') }}
@endif
contact@swapiles.com · https://swapiles.com
