---
name: ui-ux-pro-max
description: >
  Use whenever building or improving any user-facing UI in Swapiles — Blade
  views, Tailwind markup, Filament admin screens, product cards, forms,
  checkout, emails, empty states, mobile navigation, or any visual/UX change.
  Produces clean, consistent, accessible, mobile-first interfaces that match
  the existing Swap'Îles marketplace design. Trigger on: "refais le design",
  "améliore l'UI/UX", "page trop moche", "responsive", "mobile", "composant",
  "carte produit", "bouton", "formulaire", "page d'accueil", "checkout UI".
---

# UI/UX Pro Max — Swapiles

Skill de conception d'interfaces pour **Swap'Îles**, la marketplace
d'occasion des territoires insulaires (DOM-TOM). Objectif : produire une UI
de niveau pro — épurée, cohérente, rassurante, **mobile-first** — sans
réinventer le style à chaque fois.

## Stack & contraintes (à respecter)

- **Laravel 13 + Blade** — vues dans `resources/views/`, layout principal
  `resources/views/layouts/app.blade.php`.
- **Tailwind CSS 4** (via Vite) — classes utilitaires uniquement. Pas de CSS
  custom sauf nécessité réelle. Build : `npm run build`.
- **Filament 4** pour l'admin — utiliser les composants Filament natifs, ne
  pas réinventer les tables/formulaires admin.
- **Mobile-first** : la majorité du trafic est mobile. Concevoir d'abord pour
  le petit écran, puis enrichir avec `sm: md: lg:`.

## Règle d'or : réutiliser avant de créer

**Avant d'écrire une nouvelle vue ou un composant, TOUJOURS :**
1. Chercher un composant/partial existant qui fait déjà la chose
   (`resources/views/components/`, partials, `@include`).
2. Regarder 2-3 pages existantes du même type pour **copier le style réel**
   (espacements, couleurs, arrondis, ombres) — la cohérence prime sur la
   nouveauté.
3. Ne diverger du style existant que si le but explicite est un redesign.

## Principes de design

- **Hiérarchie claire** : un seul objectif principal par écran, un seul bouton
  d'action primaire visuellement dominant.
- **Espacement généreux et régulier** : échelle Tailwind (`p-2 p-3 p-4 p-6`),
  jamais de valeurs arbitraires si une classe standard existe.
- **Rayons & ombres cohérents** : réutiliser le `rounded-*` et `shadow-*`
  déjà employés sur les cartes produits existantes.
- **Confiance** (crucial pour une marketplace) : afficher avis, protection
  acheteur, badges vérifié, prix clairs (prix + protection + livraison
  détaillés), photos vendeur. La confiance = conversion.
- **États vides & de chargement** : toujours prévoir l'empty state (« Aucune
  annonce pour l'instant ») et le feedback d'action (spinner, désactivation
  du bouton pendant la soumission).
- **Toucher** : cibles tactiles ≥ 44px, boutons pleine largeur sur mobile,
  `bottom nav` mobile déjà présente — la réutiliser.

## Identité Swap'Îles

- Ton : chaleureux, local, insulaire, mais **sobre et crédible** (on gère de
  l'argent réel). Éviter le gadget/tropical criard.
- Accent couleur : réutiliser la couleur primaire déjà définie dans le thème
  (ne pas introduire une nouvelle palette sans raison).
- Emojis avec parcimonie, cohérents avec l'existant (🎉 ✅ déjà utilisés dans
  les notifications).
- Textes en **français**, ton direct et rassurant.

## Accessibilité (checklist non négociable)

- Contraste texte/fond suffisant (viser WCAG AA).
- Tout `<img>` produit a un `alt` pertinent (titre de l'annonce).
- Champs de formulaire avec `<label>` associé (`for`/`id`).
- États focus visibles (`focus:ring`), navigation clavier possible.
- Ne jamais transmettre l'info par la seule couleur (ajouter icône/texte).

## Composants types (patterns)

- **Carte produit** : image 1:1 en haut (`aspect-square object-cover`),
  favori en overlay, titre tronqué (`line-clamp-1`), prix en gras, badge
  territoire/livraison. Réutiliser le composant existant.
- **Bouton primaire** : pleine largeur mobile, couleur primaire, état
  `disabled` + spinner à la soumission.
- **Formulaire** : un champ par ligne sur mobile, labels visibles, erreurs de
  validation Laravel affichées sous le champ (`@error`).
- **Prix marketplace** : toujours détailler prix article + protection
  acheteur + livraison = total, comme au checkout existant.

## Workflow de travail (à suivre à chaque tâche UI)

1. **Localiser** la/les vue(s) concernées et les composants réutilisables.
2. **S'aligner** sur le style existant (lire le code voisin d'abord).
3. **Coder** en mobile-first, classes Tailwind, Blade propre et lisible.
4. **Vérifier le responsive** mentalement à 3 largeurs : mobile (~375px),
   tablette (`md`), desktop (`lg`).
5. **Builder** les assets si nécessaire (`npm run build`) et signaler à
   l'utilisateur de vérifier visuellement.
6. Ne jamais casser le comportement existant (liens, routes, données) — l'UI
   se greffe sur la logique en place.

## À éviter

- Introduire une lib CSS/JS externe (le projet est Tailwind-only, et les
  artefacts doivent rester self-contained).
- Réécrire un style global qui impacte d'autres pages sans le dire.
- CSS inline massif ou valeurs magiques (`style="..."`) quand une classe
  Tailwind existe.
- Modifier l'admin Filament avec du HTML custom au lieu des composants
  Filament.
