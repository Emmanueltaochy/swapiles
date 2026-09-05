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

  // Jeton reçu -> on l'envoie au serveur.
  Push.addListener('registration', function (payload) {
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
      window.location.href = url;
    }
  });

  // Demande de permission puis enregistrement.
  Push.requestPermissions().then(function (result) {
    if (result && result.receive === 'granted') {
      Push.register();
    }
  }).catch(function () {});
})();
