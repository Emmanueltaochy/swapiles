# Déploiement automatique Swapiles (GitHub → VPS Hostinger)

Ce dépôt est configuré pour un **déploiement automatique** : dès qu'un commit
arrive sur la branche `main`, GitHub Actions se connecte en SSH au VPS, récupère
le code et met le site à jour **sans perte de données** et **sans coupure**.

```
git push (main)  ──►  GitHub Actions  ──SSH──►  VPS  ──►  site à jour
```

## Ce que fait le déploiement (`scripts/deploy.sh`)

1. **Sauvegarde la base de données** (mysqldump compressé, 15 dernières conservées).
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build` (assets Vite/Tailwind).
4. `php artisan migrate --force` — **migrations additives uniquement**, jamais
   `migrate:fresh`/`refresh`, donc **aucune donnée utilisateur n'est effacée**.
5. `php artisan storage:link`
6. Reconstruction des caches (`config`, `route`, `view`) avec garde-fous.
7. `php artisan queue:restart` (les workers rechargent le nouveau code).
8. Rechargement de `php8.3-fpm` et `nginx` (**reload**, pas de coupure).

## Configuration initiale (une seule fois)

Le déploiement a besoin de **deux accès** :

### A. GitHub Actions doit pouvoir se connecter au VPS (clé SSH)

Sur le VPS :

```bash
# Génère une clé dédiée aux déploiements
ssh-keygen -t ed25519 -C "github-actions-swapiles" -f ~/.ssh/gh_deploy -N ""
# Autorise cette clé à se connecter
cat ~/.ssh/gh_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
# Affiche la clé PRIVÉE à copier dans GitHub
cat ~/.ssh/gh_deploy
```

Puis dans **GitHub → dépôt `swapiles` → Settings → Secrets and variables →
Actions → New repository secret**, crée :

| Secret | Valeur |
|--------|--------|
| `VPS_HOST` | l'IP du VPS (ex. `82.x.x.x`) |
| `VPS_USER` | `root` |
| `VPS_PORT` | `22` |
| `VPS_PATH` | `/var/www/swapiles` |
| `VPS_SSH_KEY` | **tout** le contenu de la clé privée `~/.ssh/gh_deploy` |

### B. Le VPS doit pouvoir récupérer le code depuis GitHub (clé de déploiement)

Sur le VPS :

```bash
ssh-keygen -t ed25519 -C "swapiles-vps-pull" -f ~/.ssh/github_swapiles -N ""
cat ~/.ssh/github_swapiles.pub
```

Copie cette clé **publique** dans **GitHub → dépôt `swapiles` → Settings →
Deploy keys → Add deploy key** (laisse « Allow write access » **décoché** :
lecture seule suffit).

Puis, toujours sur le VPS, indique à git d'utiliser cette clé et bascule le
dépôt en SSH :

```bash
cat >> ~/.ssh/config <<'EOF'

Host github.com
  HostName github.com
  User git
  IdentityFile ~/.ssh/github_swapiles
  IdentitiesOnly yes
EOF
chmod 600 ~/.ssh/config

cd /var/www/swapiles
git remote set-url origin git@github.com:Emmanueltaochy/swapiles.git
ssh -T git@github.com   # tape "yes" pour accepter l'empreinte
git fetch origin main   # doit fonctionner sans mot de passe
```

## Utilisation au quotidien

- Le développement se fait sur des branches, puis on fusionne dans `main`.
- Chaque fusion/push sur `main` déclenche automatiquement le déploiement.
- Suivi en direct : **onglet Actions** du dépôt GitHub.
- Déploiement manuel possible : Actions → « Déploiement VPS » → « Run workflow ».

## En cas de problème

- Les logs complets sont dans l'onglet **Actions** de GitHub.
- Les sauvegardes BDD sont sur le VPS dans `~/swapiles-db-backups/`.
- Restaurer une sauvegarde :
  `gunzip < ~/swapiles-db-backups/swapiles-AAAAMMJJ-HHMMSS.sql.gz | mysql -u USER -p BASE`
