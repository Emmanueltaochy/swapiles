#!/usr/bin/env bash
#
# Script de déploiement Swapiles — exécuté SUR LE VPS par GitHub Actions.
#
# Objectifs :
#   1. Ne JAMAIS perdre de données  -> sauvegarde BDD avant tout, migrations additives seulement
#   2. Rester en ligne              -> pas de mode maintenance, rechargement (reload) et non redémarrage
#   3. Être robuste                 -> les étapes optionnelles ne cassent pas le déploiement
#
# Le code est déjà à jour quand ce script tourne (le workflow fait git reset --hard avant).

set -euo pipefail

# Se placer à la racine du projet (le dossier parent de /scripts)
cd "$(dirname "$0")/.."
APP_DIR="$(pwd)"
echo "==> Déploiement Swapiles dans : $APP_DIR"

# Charger nvm si présent (sinon npm/node peuvent être introuvables en session non interactive)
export PATH="$PATH:/usr/local/bin:/usr/bin"
if [ -s "$HOME/.nvm/nvm.sh" ]; then . "$HOME/.nvm/nvm.sh"; fi

# ---------------------------------------------------------------------------
# 0. SAUVEGARDE DE LA BASE DE DONNÉES (filet de sécurité avant migrations)
# ---------------------------------------------------------------------------
BACKUP_DIR="$HOME/swapiles-db-backups"
mkdir -p "$BACKUP_DIR"
if [ -f .env ]; then
  clean() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed -e 's/^["'\'']//' -e 's/["'\'']$//' -e 's/\r$//'; }
  DB_CONNECTION="$(clean DB_CONNECTION || true)"
  if [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
    DB_DATABASE="$(clean DB_DATABASE)"
    DB_USERNAME="$(clean DB_USERNAME)"
    DB_PASSWORD="$(clean DB_PASSWORD)"
    BACKUP_FILE="$BACKUP_DIR/swapiles-$(date +%Y%m%d-%H%M%S).sql"
    echo "==> Sauvegarde BDD -> $BACKUP_FILE"
    if mysqldump --no-tablespaces --single-transaction -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > "$BACKUP_FILE" 2>/dev/null; then
      gzip -f "$BACKUP_FILE" || true
      echo "    Sauvegarde OK"
    else
      echo "    !! Sauvegarde BDD échouée — on continue (vérifie les identifiants .env)"
      rm -f "$BACKUP_FILE"
    fi
    # Ne conserver que les 15 dernières sauvegardes
    ls -1t "$BACKUP_DIR"/swapiles-*.sql.gz 2>/dev/null | tail -n +16 | xargs -r rm -f
  fi
fi

# ---------------------------------------------------------------------------
# 1. DÉPENDANCES PHP (production)
# ---------------------------------------------------------------------------
echo "==> composer install (prod)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ---------------------------------------------------------------------------
# 2. BUILD FRONT-END (Vite)
# ---------------------------------------------------------------------------
if [ -f package.json ]; then
  echo "==> npm ci && npm run build"
  npm ci --no-audit --no-fund
  npm run build
fi

# ---------------------------------------------------------------------------
# 3. MIGRATIONS — ADDITIVES UNIQUEMENT (jamais fresh/refresh => zéro perte)
# ---------------------------------------------------------------------------
echo "==> php artisan migrate --force"
php artisan migrate --force

# ---------------------------------------------------------------------------
# 4. LIEN SYMBOLIQUE storage (idempotent, pour les images publiques)
# ---------------------------------------------------------------------------
php artisan storage:link 2>/dev/null || true

# ---------------------------------------------------------------------------
# 5. CACHES D'OPTIMISATION (avec garde-fous pour ne jamais casser le site)
# ---------------------------------------------------------------------------
echo "==> Reconstruction des caches"
php artisan optimize:clear >/dev/null 2>&1 || true
# IMPORTANT : on NE met PAS config:cache en cache tant que du code applicatif
# lit des variables via env() au runtime (Stripe, Colissimo). Avec config:cache,
# ces env() renvoient null. On garde donc la config non cachée (env() fonctionne).
# À réactiver (php artisan config:cache) une fois tous les env() migrés vers config().
php artisan config:clear >/dev/null 2>&1 || true
php artisan view:cache
# route:cache échoue si des routes utilisent des closures : dans ce cas on nettoie
# plutôt que de laisser un cache cassé (le site reste fonctionnel).
if ! php artisan route:cache 2>/dev/null; then
  echo "    route:cache impossible (closures dans les routes) -> route:clear"
  php artisan route:clear || true
fi

# ---------------------------------------------------------------------------
# 5c. QUALITÉ : masquer les annonces publiées sans photo (conversion recherche)
#     Idempotent : après le premier passage il n'en reste plus, les suivants
#     ne font rien. Ne bloque jamais le déploiement.
# ---------------------------------------------------------------------------
php artisan listings:hide-photoless 2>/dev/null || true

# Aligner le territoire des membres sur leur code postal DOM-TOM (corrige les
# profils « La Réunion » par défaut alors que l'adresse est ailleurs). Idempotent.
php artisan users:sync-territoire-from-postal 2>/dev/null || true

# ---------------------------------------------------------------------------
# 6. REDÉMARRER LES WORKERS DE QUEUE (pour qu'ils chargent le nouveau code)
# ---------------------------------------------------------------------------
php artisan queue:restart 2>/dev/null || true

# ---------------------------------------------------------------------------
# 7. PERMISSIONS (le serveur web doit pouvoir écrire dans storage / cache)
# ---------------------------------------------------------------------------
WEB_USER="${WEB_USER:-www-data}"
chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

# ---------------------------------------------------------------------------
# 8. RECHARGER PHP-FPM ET NGINX (reload = pas de coupure)
# ---------------------------------------------------------------------------
echo "==> Rechargement des services"
if command -v systemctl >/dev/null 2>&1; then
  systemctl reload php8.3-fpm 2>/dev/null || systemctl restart php8.3-fpm 2>/dev/null || true
  systemctl reload nginx 2>/dev/null || true
fi

echo "==> Déploiement terminé ✅"
