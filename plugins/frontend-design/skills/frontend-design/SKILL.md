---
name: frontend-design
description: >
  Use when implementing or polishing front-end code in Swapiles — writing or
  refactoring Blade templates and Tailwind markup, building responsive layouts,
  fixing spacing/typography/alignment, adding transitions and micro-interactions,
  optimizing perceived performance (image loading, layout shift), or decomposing
  UI into reusable Blade components. This is the implementation-craft companion
  to ui-ux-pro-max (which covers UX/design decisions). Trigger on: "intègre",
  "mets en page", "responsive", "aligne", "espacement", "typo", "animation",
  "transition", "composant Blade", "refactor la vue", "optimise le front".
---

# Frontend Design — Craft d'implémentation Swapiles

Skill pour **écrire du front de qualité** dans Swap'Îles : markup propre,
système Tailwind cohérent, responsive solide, perf perçue. Il complète
`ui-ux-pro-max` (décisions UX) en se concentrant sur le **comment coder**.

## Stack

- **Blade** (`resources/views/`) + composants (`resources/views/components/`).
- **Tailwind CSS 4** via Vite (`resources/css/app.css`, `npm run build`).
- Pas de framework JS lourd : Blade + un peu d'Alpine.js/vanilla si présent.
  Ne pas introduire React/Vue.

## HTML/Blade sémantique

- Balises justes : `<button>` pour agir, `<a>` pour naviguer, `<nav> <main>
  <header> <section> <article>`. Pas de `<div onclick>`.
- Composants Blade réutilisables (`<x-...>`) pour tout élément répété (carte
  produit, badge, bouton). Un composant = une responsabilité.
- Passer les données par props/slots, pas de logique métier dans la vue.
- `@error`, `@csrf`, `@method` corrects sur les formulaires.

## Système Tailwind (cohérence avant tout)

- **Échelle d'espacement** : s'en tenir à `2 / 3 / 4 / 6 / 8 / 12`. Éviter les
  valeurs arbitraires `[13px]` si une classe standard convient.
- **Typographie** : échelle `text-sm / base / lg / xl / 2xl`, poids `medium /
  semibold / bold` — réutiliser ce que font les pages existantes.
- **Couleurs** : utiliser les tokens du thème (couleur primaire déjà définie),
  jamais de hex en dur si un token existe.
- **Rayons/ombres** : un seul langage visuel (`rounded-xl`, `shadow-sm/md`) —
  copier celui des cartes existantes.
- Regrouper les variants logiquement : `base → sm: → md: → lg:` et états
  `hover: focus: disabled:` dans cet ordre pour la lisibilité.

## Responsive (mobile-first, obligatoire)

- Écrire les classes **pour mobile d'abord**, enrichir avec `sm: md: lg:`.
- Grilles : `grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4` pour les
  listes d'annonces.
- Vérifier 3 largeurs : **~375px** (mobile), **md** (~768px), **lg** (≥1024px).
- Jamais de débordement horizontal : contenus larges (tableaux, media) dans un
  conteneur `overflow-x-auto`.
- Cibles tactiles ≥ 44px, boutons `w-full` sur mobile.

## Micro-interactions & feedback

- Transitions douces et discrètes : `transition`, `duration-150/200`,
  `hover:` sur les éléments cliquables. Pas d'animation gadget.
- Feedback d'action : bouton `disabled` + spinner pendant une soumission ;
  toasts/flash Laravel (`session('status')`) pour confirmer.
- `focus-visible:ring` sur tous les interactifs (clavier).

## Performance perçue

- Images produits : `loading="lazy"`, dimensions fixées (`aspect-square`,
  `w-full h-full object-cover`) pour **éviter le layout shift (CLS)**.
- Toujours un `alt` pertinent (SEO + accessibilité).
- Pas de gros JS bloquant ; assets buildés par Vite (`npm run build`).
- Éviter le contenu qui « saute » : réserver l'espace des images/skeletons.

## Workflow

1. **Lire** la vue cible + 1-2 vues voisines du même type pour copier le style.
2. **Chercher** un composant Blade existant avant d'en créer un.
3. **Coder** mobile-first, classes ordonnées, markup sémantique.
4. **Factoriser** si un bloc se répète (extraire un `<x-...>`).
5. **Builder** (`npm run build`) et demander une vérification visuelle.
6. Ne pas casser routes/données existantes — le front se greffe sur la logique.

## À éviter

- Introduire une lib CSS/JS externe (projet Tailwind-only, self-contained).
- CSS inline ou `!important` quand une classe Tailwind fait le travail.
- Dupliquer un même bloc de markup au lieu d'un composant Blade.
- Toucher au style global sans mesurer l'impact sur les autres pages.
- Refaire en HTML custom ce que Filament fournit déjà côté admin.
