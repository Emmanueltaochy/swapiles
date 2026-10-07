/**
 * Notifications push — pont entre le site (chargé dans la coque Capacitor) et
 * le natif. Ne fait RIEN sur le web classique : le code ne s'exécute que si la
 * page tourne à l'intérieur de l'application mobile.
 *
 * Au lancement :
 *   1. demande la permission de notification,
 *   2. enregistre l'appareil auprès de FCM,
 *   3. envoie le jeton obtenu au serveur (/push/register),
 *   4. ouvre le bon écran quand l'utilisateur tape sur une notification.
 */
(function () {
  var cap = window.Capacitor;

  // Hors application native (navigateur web) : on ne fait rien.
  if (!cap || typeof cap.isNativePlatform !== 'function' || !cap.isNativePlatform()) {
    return;
  }

  // La page est chargée : on masque l'écran de démarrage (handoff propre, pas
  // d'écran blanc entre le splash et l'affichage du site).
  try {
    var Splash = cap.Plugins && cap.Plugins.SplashScreen;
    if (Splash && typeof Splash.hide === 'function') {
      Splash.hide();
    }
  } catch (e) { /* sans gravité */ }

  var Push = cap.Plugins && cap.Plugins.PushNotifications;
  if (!Push) {
    return;
  }

  var platform = (typeof cap.getPlatform === 'function') ? cap.getPlatform() : null;

  function sendToken(token) {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrf = meta ? meta.getAttribute('content') : '';

    try {
      fetch('/push/register', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ token: token, platform: platform }),
      });
    } catch (e) {
      /* silencieux : on réessaiera au prochain lancement */
    }
  }

  // Pastille de l'icône (le petit chiffre) : remise à zéro quand on ouvre
  // l'appli ou qu'on y revient — les non-lus restent signalés DANS l'appli
  // (cloche, messages). Le prochain envoi affichera le vrai nombre de non-lus.
  // iOS n'accepte cette remise à zéro qu'une fois l'appareil enregistré.
  var enregistre = false;
  function effacerPastille() {
    if (!enregistre || typeof Push.removeAllDeliveredNotifications !== 'function') return;
    try {
      Push.removeAllDeliveredNotifications().catch(function () {});
    } catch (e) { /* sans gravité */ }
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') effacerPastille();
  });

  // Jeton reçu -> on l'envoie au serveur.
  Push.addListener('registration', function (payload) {
    enregistre = true;
    effacerPastille();
    if (payload && payload.value) {
      sendToken(payload.value);
    }
  });

  Push.addListener('registrationError', function () {
    /* on ignore : nouvelle tentative au prochain lancement */
  });

  // Tap sur une notification -> ouvrir l'URL éventuelle.
  Push.addListener('pushNotificationActionPerformed', function (action) {
    var data = action && action.notification && action.notification.data;
    var url = data && data.url;
    if (url) {
      // Sans rechargement quand c'est possible : l'appli reste « chaude ».
      if (window.Turbo && typeof window.Turbo.visit === 'function') {
        window.Turbo.visit(url);
      } else {
        window.location.href = url;
      }
    }
  });

  // Demande de permission puis enregistrement.
  Push.requestPermissions().then(function (result) {
    if (result && result.receive === 'granted') {
      Push.register();
    }
  }).catch(function () {});
})();
