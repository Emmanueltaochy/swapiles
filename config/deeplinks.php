<?php

/*
|--------------------------------------------------------------------------
| Liens qui ouvrent l'application (App Links / Universal Links)
|--------------------------------------------------------------------------
|
| Quand un membre clique sur un lien swapiles.com reçu par e-mail (mot de
| passe oublié, confirmation d'inscription, annonce…), le téléphone ouvre le
| navigateur. Avec ces déclarations, il ouvre DIRECTEMENT l'application si
| elle est installée — sans rien demander à l'utilisateur.
|
| Trois éléments sont nécessaires, et les trois doivent être en place :
|   1. ces deux fichiers servis par le site (ici) ;
|   2. côté Android, l'empreinte SHA-256 du certificat de signature ;
|   3. côté natif, la déclaration dans l'app (nouveau build des deux côtés).
|
| L'empreinte Android se trouve dans la Play Console :
|   Test et publication > Intégrité de l'app > Certificat de la clé de
|   signature d'app > empreinte SHA-256.
| À renseigner dans ANDROID_SHA256_FINGERPRINT (secret GitHub).
|
| Tant que l'empreinte n'est pas fournie, le fichier Android n'est pas servi :
| un fichier incomplet empêcherait la vérification plutôt que de l'aider.
|
*/

return [

    'android' => [
        'package' => env('ANDROID_PACKAGE_NAME', 'com.swapiles.app'),
        // Plusieurs empreintes possibles, séparées par des virgules
        // (clé de signature Play + clé d'upload, par exemple).
        'sha256' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ANDROID_SHA256_FINGERPRINT', ''))
        ))),
    ],

    'ios' => [
        'team_id' => env('APNS_TEAM_ID'),
        'bundle_id' => env('APNS_BUNDLE_ID', 'com.swapiles.app'),
    ],

];
