# Ressources visuelles de l'app

Dépose ici les sources ; `npm run assets` génère toutes les tailles.

- **icon.png** — icône de l'app, **1024×1024 px**, PNG **sans transparence**
  (fond plein). C'est l'icône sur l'écran d'accueil. Le logo texte actuel
  (288×96) ne convient pas : il faut un visuel **carré**.
- **splash.png** — écran de démarrage, **2732×2732 px**, sujet **centré**
  (les bords sont rognés selon les écrans). Fond conseillé : teal `#0f766e`.

Puis :

```
cd mobile
npm run assets
npx cap sync
```
