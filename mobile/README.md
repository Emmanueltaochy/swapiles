# Swap'Îles — application mobile (Capacitor)

Cette coque **Capacitor** publie Swap'Îles sur l'App Store et le Play Store.
Elle **affiche le site en direct** (`https://swapiles.com`) : c'est le même
Swap'Îles que sur le web, dans une vraie app.

## 🔑 Le point le plus important : les mises à jour

| Ce que tu changes | Repasser par les stores ? |
|---|---|
| Une **fonctionnalité** (code Laravel, page, bouton…) | ❌ **Non** — tu push sur `main`, ça se déploie, l'app l'affiche **tout de suite**. |
| La **coque native** (icône, nom, splash, permissions, plugin natif) | ✅ Oui — mais c'est **rare** (quelques fois par an). |

Autrement dit : tu gardes ton workflow actuel « je push, c'est en ligne ».
L'app suit automatiquement, sans re-soumission.

---

## Ce que TU dois fournir (une seule fois)

1. **Compte Apple Developer** — 99 €/an → https://developer.apple.com/programs/
2. **Compte Google Play Console** — 25 $ une fois → https://play.google.com/console/
3. **Une icône carrée 1024×1024 px** (PNG, sans transparence) — le logo actuel
   (288×96) ne convient pas comme icône. Dépose-la dans
   `mobile/resources/icon.png`.
4. (Optionnel) **Un visuel de splash 2732×2732 px** dans
   `mobile/resources/splash.png`.

---

## Option A — Tout depuis Windows via Codemagic (recommandé)

Codemagic compile **iOS ET Android** dans le cloud, signe, et envoie aux
stores — sans Mac. C'est le plus confortable pour toi.

1. Va sur https://codemagic.io/ → connecte ton dépôt GitHub `Emmanueltaochy/swapiles`.
2. Crée une application, **racine du projet = `mobile/`**.
3. Dans le workflow (build script), avant le build natif :
   ```
   npm ci
   npx cap add android
   npx cap add ios
   npx cap sync
   ```
4. Renseigne la **signature** :
   - iOS : connecte ton compte Apple (App Store Connect API key) — Codemagic
     gère les certificats automatiquement.
   - Android : Codemagic génère et stocke ta clé de signature (keystore).
5. Active la **publication automatique** vers TestFlight (iOS) et la piste
   interne du Play Store (Android).
6. Lance le build → tu obtiens l'app en test, puis tu publies depuis
   App Store Connect / Play Console.

> Ton Mac reste un filet de secours si tu veux un jour builder en local.

---

## Option B — En local

### Android (fonctionne sous Windows)

1. Installe **Node.js** et **Android Studio**.
2. Dans un terminal :
   ```
   cd mobile
   npm install
   npx cap add android
   npm run assets        # génère les icônes/splash depuis mobile/resources/
   npx cap sync
   npx cap open android
   ```
3. Dans Android Studio : **Build > Generate Signed Bundle (.aab)** → crée ta
   clé de signature → récupère le `.aab`.
4. Envoie le `.aab` sur **Play Console** (piste interne d'abord, puis production).

### iOS (nécessite ton Mac + Xcode)

```
cd mobile
npm install
npx cap add ios
npm run assets
npx cap sync
npx cap open ios
```
Dans Xcode : choisis ton équipe Apple, archive (**Product > Archive**), puis
**Distribute App > App Store Connect**.

---

## Après une mise à jour de la coque (icône, plugin, version)

```
cd mobile
npx cap sync
```
Puis rebuild + re-soumets (Codemagic ou local). Pense à incrémenter le numéro
de version dans `capacitor.config.json` / les projets natifs.

---

## ⚠️ À anticiper

- **Validation Apple (règle 4.2)** : Apple peut refuser une app perçue comme
  « un simple site web ». Pour maximiser les chances : icône/splash soignés,
  **notifications push natives**, expérience fluide. Une marketplace réelle
  avec paiement passe en général, mais prévois 1 à 2 allers-retours.
  Le **Play Store (Android) est bien plus permissif** → publie Android
  d'abord, c'est le chemin rapide.
- **Paiements** : Stripe pour des objets d'occasion **physiques** est autorisé
  par Apple (pas d'achat in-app imposé). Le checkout actuel reste tel quel. ✅
- **Notifications push** : le plugin est inclus, mais le brancher réellement
  demande FCM (Android) + APNs (iOS) + l'envoi côté serveur. À faire dans un
  second temps — l'app v1 peut sortir sans.
- **Hors-ligne** : l'app charge le site en direct ; sans connexion, l'écran de
  repli (`www/index.html`) s'affiche. Un mode hors-ligne avancé viendrait plus
  tard (service worker / PWA).

---

## Résumé du plan

1. Prendre les comptes Apple + Google, préparer l'icône 1024×1024.
2. Brancher **Codemagic** sur le dépôt (dossier `mobile/`).
3. Publier **Android** (rapide), puis **iOS** (prévoir la revue Apple).
4. Ensuite : ajouter les **push natives** pour l'effet « accro » + démarchage.
