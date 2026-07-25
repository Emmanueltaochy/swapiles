#!/usr/bin/env bash
#
# Déploiement Block 1 (pricing) avec contrôle d'intégrité de la migration
# DECIMAL(10,2) — points de contrôle #1 (compare avant/après) et #2 (backfill).
#
# À lancer SUR LA PROD (clés live, MySQL). N'exécute QUE les migrations du
# Block 1, dans l'ordre, avec dump avant/après et ROLLBACK AUTOMATIQUE si une
# seule valeur montant a bougé.
#
# Prérequis : sauvegarde BDD complète déjà faite (mysqldump) AVANT ce script.
#
set -euo pipefail

cd "$(dirname "$0")/.."

DECIMAL_MIGRATION="2026_07_25_184526_money_columns_to_decimal_on_transactions"
BEFORE="storage/app/block1_before.json"
AFTER="storage/app/block1_after.json"

echo "==> 1/5  Dump AVANT migration ($BEFORE)"
php artisan transactions:snapshot-money > "$BEFORE"
ROWS_BEFORE=$(php -r '$d=json_decode(file_get_contents($argv[1]),true); echo count($d);' "$BEFORE")
echo "    $ROWS_BEFORE transactions capturées."

echo "==> 2/5  Migration DECIMAL(10,2) uniquement"
php artisan migrate --path="database/migrations/${DECIMAL_MIGRATION}.php" --force

echo "==> 3/5  Dump APRÈS migration ($AFTER)"
php artisan transactions:snapshot-money > "$AFTER"

echo "==> 4/5  Comparaison ligne à ligne (valeurs numériques)"
if php -r '
    $b = json_decode(file_get_contents($argv[1]), true);
    $a = json_decode(file_get_contents($argv[2]), true);
    if (count($b) !== count($a)) { fwrite(STDERR, "Nb de lignes différent: ".count($b)." -> ".count($a)."\n"); exit(1); }
    $index = [];
    foreach ($a as $row) { $index[$row["id"]] = $row; }
    $diffs = 0;
    foreach ($b as $row) {
        $id = $row["id"];
        if (!isset($index[$id])) { fwrite(STDERR, "Ligne #$id disparue après migration\n"); $diffs++; continue; }
        foreach ($row as $k => $v) {
            $after = $index[$id][$k] ?? null;
            if (is_numeric($v) && is_numeric($after)) {
                if (abs((float)$v - (float)$after) > 0.0001) { fwrite(STDERR, "Ligne #$id: $k $v -> $after\n"); $diffs++; }
            } elseif ($v !== $after) {
                fwrite(STDERR, "Ligne #$id: $k ".json_encode($v)." -> ".json_encode($after)."\n"); $diffs++;
            }
        }
    }
    if ($diffs > 0) { fwrite(STDERR, "$diffs différence(s) détectée(s)\n"); exit(1); }
    fwrite(STDOUT, "Aucune valeur modifiée : intégrité OK.\n");
' "$BEFORE" "$AFTER"; then
    echo "    Intégrité confirmée."
else
    echo "!!  DIFFÉRENCE DÉTECTÉE — ROLLBACK IMMÉDIAT de la migration DECIMAL"
    php artisan migrate:rollback --path="database/migrations/${DECIMAL_MIGRATION}.php" --force
    echo "!!  Migration annulée. Déploiement interrompu. Vérifie le diff ci-dessus."
    exit 1
fi

echo "==> 5/5  Migrations restantes du Block 1 (backfill seller_amount + customer id)"
php artisan migrate --force

echo "==> Terminé. Pense à recharger la config si besoin (php artisan config:clear)."
