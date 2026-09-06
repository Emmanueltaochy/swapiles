#!/usr/bin/env bash
# Écrit ou met à jour une clé dans le fichier .env de production.
#
# Usage : ./scripts/set-env.sh MAIL_HOST smtp-relay.brevo.com
#
# Utilisé par le déploiement pour injecter les identifiants SMTP fournis via
# les secrets GitHub : on peut ainsi changer de fournisseur d'e-mail sans
# aucun accès SSH au serveur.
set -e

CLE="$1"
VALEUR="$2"
ENV_FILE="${3:-.env}"

if [ -z "$CLE" ]; then
  echo "set-env.sh : nom de clé manquant" >&2
  exit 1
fi

touch "$ENV_FILE"

# On échappe les caractères spéciaux pour sed et on entoure la valeur de
# guillemets (mots de passe avec des espaces ou des caractères #).
VALEUR_ECHAPPEE=$(printf '%s' "$VALEUR" | sed -e 's/[\/&]/\\&/g')

if grep -qE "^${CLE}=" "$ENV_FILE"; then
  sed -i -E "s/^${CLE}=.*/${CLE}=\"${VALEUR_ECHAPPEE}\"/" "$ENV_FILE"
else
  printf '%s="%s"\n' "$CLE" "$VALEUR" >> "$ENV_FILE"
fi
