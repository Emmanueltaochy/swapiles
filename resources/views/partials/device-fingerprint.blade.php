{{-- Empreinte d'appareil calculée côté client (traçabilité anti-multicompte).
     Écrit un hash hexadécimal dans les inputs cachés name="device_fingerprint"
     avant l'envoi du formulaire. Aucune lib tierce, aucune donnée envoyée
     ailleurs que dans le formulaire de connexion/inscription. --}}
<script>
(function () {
    function h(str) {
        var hash = 5381;
        for (var i = 0; i < str.length; i++) {
            hash = ((hash << 5) + hash) + str.charCodeAt(i);
            hash = hash & 0xffffffff;
        }
        return ('00000000' + (hash >>> 0).toString(16)).slice(-8);
    }
    function canvasFp() {
        try {
            var c = document.createElement('canvas');
            var ctx = c.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = "14px 'Arial'";
            ctx.fillStyle = '#f60';
            ctx.fillRect(125, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText("swap'iles", 2, 15);
            return c.toDataURL();
        } catch (e) {
            return 'nocanvas';
        }
    }
    try {
        var parts = [
            (screen.width || 0) + 'x' + (screen.height || 0),
            screen.colorDepth || 0,
            new Date().getTimezoneOffset(),
            navigator.language || '',
            navigator.platform || '',
            navigator.hardwareConcurrency || '',
            (navigator.userAgent || '').length,
            canvasFp()
        ];
        var joined = parts.join('|');
        // 4 sous-hashes = 32 caractères hex, stable pour un même appareil/navigateur.
        var fp = h(joined) + h(parts.reverse().join('|')) + h(joined + 'x') + h('y' + joined);
        var inputs = document.querySelectorAll('input[name=device_fingerprint]');
        for (var k = 0; k < inputs.length; k++) {
            inputs[k].value = fp;
        }
    } catch (e) {}
})();
</script>
