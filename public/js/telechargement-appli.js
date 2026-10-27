/**
 * Téléchargements dans l'application (admin).
 *
 * Pourquoi ce fichier : dans l'appli (coque Capacitor), un fichier téléchargé
 * (PDF, export Excel) s'affichait à la place de l'écran, sans barre ni bouton
 * retour : impossible d'en sortir. Les téléchargements de l'admin passent par
 * un lien « blob » cliqué par Livewire. Dans l'appli seulement, on intercepte
 * ce clic et on ouvre le fichier dans une fenêtre avec « Fermer » et
 * « Enregistrer / partager » (menu du téléphone : Fichiers, Mail…).
 * Sur ordinateur et dans un navigateur, rien ne change.
 */
(function () {
    'use strict';

    var cap = window.Capacitor;
    if (!(cap && typeof cap.isNativePlatform === 'function' && cap.isNativePlatform())) return;

    // Garder le fichier lui-même : Livewire libère son adresse aussitôt après le clic.
    var fichiers = {};
    [window.URL, window.webkitURL].forEach(function (U) {
        if (!U || U._swpPatch) return;
        var creer = U.createObjectURL;
        U.createObjectURL = function (objet) {
            var adresse = creer.call(U, objet);
            if (objet instanceof Blob) fichiers[adresse] = objet;
            return adresse;
        };
        U._swpPatch = true;
    });

    var cliquer = HTMLAnchorElement.prototype.click;
    HTMLAnchorElement.prototype.click = function () {
        var adresse = this.getAttribute('href') || '';
        if (this.hasAttribute('download') && fichiers[adresse]) {
            var fichier = fichiers[adresse];
            delete fichiers[adresse];
            ouvrir(fichier, this.getAttribute('download') || 'fichier');
            return;
        }
        return cliquer.call(this);
    };

    function bouton(texte, principal) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = texte;
        b.style.cssText = 'appearance:none;border:0;border-radius:12px;padding:10px 14px;font-weight:700;font-size:15px;'
            + (principal ? 'background:#0f766e;color:#fff' : 'background:#f3f4f6;color:#111827');
        return b;
    }

    function ouvrir(blob, nom) {
        var adresse = URL.createObjectURL(blob);
        var fond = document.createElement('div');
        fond.style.cssText = 'position:fixed;inset:0;z-index:2147483647;background:#fff;display:flex;flex-direction:column;'
            + 'padding-top:env(safe-area-inset-top)';

        var barre = document.createElement('div');
        barre.style.cssText = 'display:flex;gap:8px;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid #e5e7eb';
        var fermer = bouton('← Fermer', false);
        var partager = bouton('📄 Enregistrer / partager', true);
        barre.appendChild(fermer);
        barre.appendChild(partager);

        var titre = document.createElement('div');
        titre.textContent = nom;
        titre.style.cssText = 'padding:8px 12px;font-size:13px;color:#6b7280;word-break:break-all';

        var apercu = document.createElement('iframe');
        apercu.src = adresse;
        apercu.title = nom;
        apercu.style.cssText = 'flex:1;width:100%;border:0;background:#f9fafb';

        fond.appendChild(barre);
        fond.appendChild(titre);
        fond.appendChild(apercu);
        document.body.appendChild(fond);

        fermer.addEventListener('click', function () {
            fond.remove();
            URL.revokeObjectURL(adresse);
        });

        partager.addEventListener('click', function () {
            var f = new File([blob], nom, { type: blob.type || 'application/octet-stream' });
            if (typeof navigator.share === 'function' && navigator.canShare && navigator.canShare({ files: [f] })) {
                navigator.share({ files: [f], title: nom }).catch(function () { /* partage annulé */ });
            } else {
                window.alert('Le partage n’est pas disponible sur ce téléphone. Ouvrez l’admin dans votre navigateur pour télécharger ce fichier.');
            }
        });
    }
})();
