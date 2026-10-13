/**
 * Appli mobile : compte les installations réellement utilisées.
 *
 * Au premier lancement, l'appli tire un identifiant AU HASARD et le garde
 * dans son stockage (il disparaît si l'appli est désinstallée). À chaque
 * lancement, elle dit au site « je suis ouverte, sur iPhone ou Android ».
 * Ni identifiant de l'appareil, ni lien avec le compte du membre.
 * Sur le site internet (navigateur), ce script ne fait rien.
 */
(function () {
    'use strict';

    var cap = window.Capacitor;
    if (!(cap && typeof cap.isNativePlatform === 'function' && cap.isNativePlatform())) return;

    var plateforme = typeof cap.getPlatform === 'function' ? cap.getPlatform() : '';
    if (plateforme !== 'ios' && plateforme !== 'android') return;

    // Une fois par lancement : la session de l'appli repart à zéro à chaque ouverture.
    try {
        if (sessionStorage.getItem('swp_ouverture_comptee')) return;
        sessionStorage.setItem('swp_ouverture_comptee', '1');
    } catch (e) { /* stockage indisponible : on compte quand même */ }

    var id = null;
    try {
        id = localStorage.getItem('swp_installation');
        if (!id) {
            id = (window.crypto && typeof window.crypto.randomUUID === 'function')
                ? window.crypto.randomUUID()
                : 'i' + Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
            localStorage.setItem('swp_installation', id);
        }
    } catch (e) {
        return;
    }

    var jeton = document.querySelector('meta[name="csrf-token"]');

    fetch('/appli/installation', {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': jeton ? jeton.getAttribute('content') : '',
        },
        body: JSON.stringify({ id: id, plateforme: plateforme }),
    }).catch(function () { /* hors ligne : ce sera pour le prochain lancement */ });
})();
