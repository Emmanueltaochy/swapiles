/**
 * « 📸 Partager en story » : fabrique sur le téléphone une image verticale
 * (format story Instagram / WhatsApp, 1080×1920) avec la photo de l'article,
 * son titre, son prix et le logo Swap'Îles, puis ouvre le partage du
 * téléphone. Si le partage de fichier n'est pas possible, l'image s'affiche
 * pour être enregistrée (appui long) ou téléchargée.
 */
(function () {
    'use strict';

    if (window.swpStoryPartage) return;
    window.swpStoryPartage = true;

    var L = 1080, H = 1920;

    function charger(src) {
        return new Promise(function (ok) {
            if (!src) return ok(null);
            var img = new Image();
            try {
                if (new URL(src, location.href).origin !== location.origin) img.crossOrigin = 'anonymous';
            } catch (e) {}
            img.onload = function () { ok(img); };
            img.onerror = function () { ok(null); };
            img.src = src;
        });
    }

    function arrondi(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function couvrir(ctx, img, x, y, w, h) {
        var ratio = Math.max(w / img.width, h / img.height);
        var sw = w / ratio, sh = h / ratio;
        ctx.drawImage(img, (img.width - sw) / 2, (img.height - sh) / 2, sw, sh, x, y, w, h);
    }

    function lignes(ctx, texte, largeur, max) {
        var mots = String(texte || '').split(/\s+/), res = [], ligne = '';
        mots.forEach(function (m) {
            var essai = ligne ? ligne + ' ' + m : m;
            if (ctx.measureText(essai).width > largeur && ligne) { res.push(ligne); ligne = m; } else { ligne = essai; }
        });
        if (ligne) res.push(ligne);
        if (res.length > max) { res = res.slice(0, max); res[max - 1] = res[max - 1].replace(/\s*\S*$/, '') + '…'; }
        return res;
    }

    function dessiner(d, photo, logo) {
        var c = document.createElement('canvas');
        c.width = L; c.height = H;
        var ctx = c.getContext('2d');

        // Fond : dégradé aux couleurs du site.
        var g = ctx.createLinearGradient(0, 0, 0, H);
        g.addColorStop(0, '#0f766e'); g.addColorStop(1, '#064e3b');
        ctx.fillStyle = g; ctx.fillRect(0, 0, L, H);

        // Logo en haut, sur un bandeau blanc arrondi.
        ctx.fillStyle = '#ffffff';
        arrondi(ctx, 300, 90, 480, 130, 65); ctx.fill();
        if (logo) {
            var lh = 90, lw = logo.width * (lh / logo.height);
            ctx.drawImage(logo, (L - Math.min(lw, 420)) / 2, 110, Math.min(lw, 420), lh);
        } else {
            ctx.fillStyle = '#0f766e'; ctx.font = '800 64px system-ui, -apple-system, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText("Swap'Îles", L / 2, 178);
        }

        // Photo de l'article.
        var px = 80, py = 280, pw = L - 160, ph = 1060;
        ctx.save(); arrondi(ctx, px, py, pw, ph, 48); ctx.clip();
        ctx.fillStyle = '#e5e7eb'; ctx.fillRect(px, py, pw, ph);
        if (photo) couvrir(ctx, photo, px, py, pw, ph);
        ctx.restore();

        // Pastille prix sur la photo.
        ctx.font = '800 92px system-ui, -apple-system, sans-serif';
        var prixL = ctx.measureText(d.prix).width + 80;
        ctx.fillStyle = '#ffffff';
        arrondi(ctx, px + 36, py + ph - 156, prixL, 120, 60); ctx.fill();
        ctx.fillStyle = '#0f172a'; ctx.textAlign = 'left';
        ctx.fillText(d.prix, px + 76, py + ph - 64);

        // Titre, île, vendeur.
        ctx.fillStyle = '#ffffff'; ctx.textAlign = 'left';
        ctx.font = '800 66px system-ui, -apple-system, sans-serif';
        lignes(ctx, d.titre, L - 160, 2).forEach(function (l, i) { ctx.fillText(l, 80, 1440 + i * 80); });
        ctx.font = '500 44px system-ui, -apple-system, sans-serif';
        ctx.fillStyle = 'rgba(255,255,255,.85)';
        ctx.fillText('📍 ' + d.ile + (d.vendeur ? '  ·  ' + d.vendeur : ''), 80, 1620);

        // Appel à l'action.
        ctx.fillStyle = '#fbbf24';
        arrondi(ctx, 80, 1700, L - 160, 130, 65); ctx.fill();
        ctx.fillStyle = '#0f172a'; ctx.textAlign = 'center';
        ctx.font = '800 50px system-ui, -apple-system, sans-serif';
        ctx.fillText('Dispo sur swapiles.com 🌴', L / 2, 1784);

        return c;
    }

    function afficher(url, nom) {
        var vue = document.createElement('div');
        vue.setAttribute('role', 'dialog');
        vue.style.cssText = 'position:fixed;inset:0;z-index:10001;background:rgba(0,0,0,.92);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;gap:14px';
        vue.innerHTML = '<img alt="Image à partager" style="max-height:70vh;max-width:100%;border-radius:18px">'
            + '<p style="color:#fff;text-align:center;font:600 15px system-ui;max-width:340px">Appuyez longuement sur l’image pour l’enregistrer, puis publiez-la en story Instagram ou WhatsApp.</p>'
            + '<a download style="background:#fff;color:#111;border-radius:14px;padding:12px 18px;font:700 15px system-ui;text-decoration:none">Télécharger l’image</a>'
            + '<button type="button" style="color:#fff;font:600 15px system-ui;background:none;border:0">Fermer</button>';
        vue.querySelector('img').src = url;
        var a = vue.querySelector('a'); a.href = url; a.download = nom;
        vue.querySelector('button').onclick = function () { vue.remove(); };
        document.body.appendChild(vue);
    }

    document.addEventListener('click', function (e) {
        var bouton = e.target.closest('[data-story-partage]');
        if (!bouton) return;
        e.preventDefault();
        var d = bouton.dataset;
        var libelle = bouton.innerHTML;
        bouton.disabled = true;
        bouton.innerHTML = 'Préparation de l’image…';

        Promise.all([charger(d.photo), charger(d.logo)]).then(function (imgs) {
            var canvas = dessiner({ titre: d.titre, prix: d.prixTexte, ile: d.ile, vendeur: d.vendeur }, imgs[0], imgs[1]);
            var fini = function () { bouton.disabled = false; bouton.innerHTML = libelle; };

            try {
                canvas.toBlob(function (blob) {
                    var nom = 'swapiles-' + (d.id || 'annonce') + '.png';
                    var fichier = blob ? new File([blob], nom, { type: 'image/png' }) : null;
                    if (fichier && navigator.canShare && navigator.canShare({ files: [fichier] })) {
                        navigator.share({ files: [fichier], text: d.texte + ' ' + d.lien })
                            .catch(function () {})
                            .then(fini);
                    } else {
                        afficher(blob ? URL.createObjectURL(blob) : canvas.toDataURL('image/png'), nom);
                        fini();
                    }
                }, 'image/png');
            } catch (err) {
                // Photo d'un autre domaine qui bloque la lecture : image sans photo.
                var secours = dessiner({ titre: d.titre, prix: d.prixTexte, ile: d.ile, vendeur: d.vendeur }, null, imgs[1]);
                afficher(secours.toDataURL('image/png'), 'swapiles.png');
                fini();
            }
        });
    });
})();
