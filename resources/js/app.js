/**
 * NAVIGATION SANS RECHARGEMENT (Turbo).
 *
 * Avant : chaque toucher d'onglet rechargeait une page entière — écran figé,
 * puis blanc, puis tout redessiné, styles et scripts compris. C'est ce qui
 * trahissait « un site dans une appli ».
 *
 * Maintenant : Turbo va chercher la page suivante en arrière-plan et ne
 * remplace que le contenu. Styles, scripts et connexion restent en mémoire,
 * l'entête et la barre du bas ne clignotent plus. Le site reste servi en
 * direct : toute modification du site arrive dans l'appli sans mise à jour.
 *
 * Volontairement prudent :
 *  - les FORMULAIRES gardent leur fonctionnement d'avant (rechargement
 *    complet), sauf les recherches qui le demandent explicitement
 *    (data-turbo="true"). Connexion, dépôt d'annonce, paiement : rien ne
 *    change pour eux ;
 *  - les pages sensibles (paiement, Stripe, dépôt et modification d'annonce,
 *    messagerie, administration…) s'ouvrent toujours par un rechargement
 *    complet, comme avant.
 */
import * as Turbo from '@hotwired/turbo';

Turbo.config.forms.mode = 'optin';

// Notre propre barre de chargement (voir plus bas) remplace celle de Turbo.
Turbo.config.drive.progressBarDelay = 999999;

/**
 * Adresses qui s'ouvrent toujours par un rechargement complet.
 *
 * Soit elles redirigent vers un autre site (Stripe), soit leur page porte
 * des scripts lourds écrits pour un chargement classique (paiement, dépôt
 * d'annonce avec photos, messagerie), soit elles AGISSENT quand on les
 * charge (liste des messages marqués lus, changement d'île).
 */
const RECHARGEMENT_COMPLET = [
    '/admin',
    '/checkout/',
    '/stripe/',
    '/portefeuille/',
    '/deposer-une-annonce',
    '/mes-annonces/',
    '/messages',
    '/territoire/',
    '/transactions/',
    '/mon-compte/ventes/',
    '/magic-link/',
    '/email/',
    '/n/',
    '/connexion',
    '/inscription',
    '/deconnexion',
];

/**
 * Adresses à ne jamais précharger : les charger AGIT (messages marqués lus,
 * île changée, lien de paiement créé…). Un simple défilement ne doit pas
 * déclencher l'action.
 */
const JAMAIS_PRECHARGER = RECHARGEMENT_COMPLET;

function commencePar(chemin, liste) {
    return liste.some((prefixe) => chemin === prefixe || chemin.startsWith(prefixe));
}

function cheminDe(url) {
    try {
        return new URL(url, window.location.href).pathname;
    } catch (e) {
        return '';
    }
}

// Lien vers une page sensible : on laisse le navigateur faire un chargement
// classique (annuler « turbo:click » rend la main au navigateur).
document.addEventListener('turbo:click', (event) => {
    if (commencePar(cheminDe(event.detail.url), RECHARGEMENT_COMPLET)) {
        event.preventDefault();
    }
});

// Visite lancée autrement qu'au clic (redirection, Turbo.visit) : même règle.
document.addEventListener('turbo:before-visit', (event) => {
    if (commencePar(cheminDe(event.detail.url), RECHARGEMENT_COMPLET)) {
        event.preventDefault();
        window.location.href = event.detail.url;
    }
});

document.addEventListener('turbo:before-prefetch', (event) => {
    const lien = event.target;
    if (lien && commencePar(cheminDe(lien.href), JAMAIS_PRECHARGER)) {
        event.preventDefault();
    }
});

/**
 * PRÉCHARGEMENT AU TOUCHER.
 *
 * Turbo précharge un lien au survol de la souris — mais sur un téléphone,
 * ce « survol » n'arrive qu'au moment du clic : aucun gain. On le déclenche
 * dès que le doigt se pose (~150 ms d'avance), et on l'annule si le doigt
 * glisse, c'est-à-dire si l'on faisait simplement défiler.
 */
let lienTouche = null;

document.addEventListener('touchstart', (event) => {
    const lien = event.target.closest && event.target.closest('a[href]');
    if (!lien) return;

    lienTouche = lien;
    lien.dispatchEvent(new MouseEvent('mouseenter', { bubbles: false, cancelable: false }));
}, { capture: true, passive: true });

document.addEventListener('touchmove', () => {
    if (!lienTouche) return;

    lienTouche.dispatchEvent(new MouseEvent('mouseleave', { bubbles: false, cancelable: false }));
    lienTouche = null;
}, { capture: true, passive: true });

/**
 * BARRE DE CHARGEMENT ET ONGLET ALLUMÉ À L'INSTANT.
 *
 * Le clic sur un lien suivi par Turbo est « intercepté » : le gestionnaire
 * générique de la mise en page ne le voit plus. On acquitte donc le geste
 * ici, sur l'événement de Turbo.
 */
function barre(active) {
    const element = document.getElementById('swp-chargement');
    if (element) element.classList.toggle('actif', active);
}

document.addEventListener('turbo:click', (event) => {
    if (event.defaultPrevented) return;

    const lien = event.target;
    if (lien && lien.hasAttribute && lien.hasAttribute('data-onglet')) {
        document.querySelectorAll('[data-onglet]').forEach((onglet) => {
            onglet.classList.remove('text-teal-700');
            onglet.classList.add('text-gray-500');
        });
        lien.classList.remove('text-gray-500');
        lien.classList.add('text-teal-700');
    }

    barre(true);
});

document.addEventListener('turbo:submit-start', () => barre(true));
document.addEventListener('turbo:load', () => barre(false));
document.addEventListener('turbo:fetch-request-error', () => barre(false));
document.addEventListener('turbo:frame-missing', () => barre(false));

/**
 * AVANT DE GARDER LA PAGE EN MÉMOIRE (pour le retour arrière instantané).
 *
 * Turbo photographie la page qu'on quitte et la réaffiche telle quelle au
 * retour. Il ne faut pas photographier un menu ouvert, une fenêtre de
 * signalement ou un défilement bloqué : on remet tout au repos.
 */
document.addEventListener('turbo:before-cache', () => {
    document.body.style.overflow = '';
    barre(false);

    const menu = document.getElementById('menu-mobile');
    if (menu) menu.hidden = true;
    document.querySelectorAll('[data-menu-ouvrir]').forEach((b) => b.setAttribute('aria-expanded', 'false'));

    const filtres = document.querySelector('[data-filtres-panneau]');
    if (filtres && !window.matchMedia('(min-width: 1024px)').matches) {
        filtres.classList.add('hidden');
    }

    document.querySelectorAll('[data-report-modal]').forEach((m) => m.classList.add('hidden'));
    document.querySelectorAll('details[open]').forEach((d) => d.removeAttribute('open'));

    const resultats = document.getElementById('header-search-results');
    if (resultats) resultats.classList.add('hidden');
});

/**
 * MESURE D'AUDIENCE (site web seulement — l'appli n'en charge aucune).
 *
 * Google et Meta ne comptent une page vue qu'au chargement complet. Sans ce
 * relais, toutes les pages ouvertes via Turbo disparaîtraient des
 * statistiques.
 */
let premierChargement = true;

document.addEventListener('turbo:load', () => {
    if (premierChargement) {
        premierChargement = false;
        return;
    }

    if (typeof window.gtag === 'function') {
        window.gtag('event', 'page_view', {
            page_location: window.location.href,
            page_title: document.title,
        });
    }

    if (typeof window.fbq === 'function') {
        window.fbq('track', 'PageView');
    }
});

window.Turbo = Turbo;
