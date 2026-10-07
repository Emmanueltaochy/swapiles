#!/usr/bin/env python3
"""Écrit les cookies sur le disque dès que l'appli Android passe en arrière-plan.

Le WebView Android garde les cookies en mémoire et ne les écrit sur le disque
qu'en différé (environ toutes les 30 secondes). Si l'appli est fermée avant —
par exemple juste après la connexion —, le cookie de connexion est perdu et
le membre se retrouve déconnecté au lancement suivant. Capacitor ne force pas
cette écriture : on l'ajoute dans MainActivity, à chaque passage en
arrière-plan (onPause), ce qui précède toujours la fermeture de l'appli.

Le projet Android étant régénéré à chaque build (npx cap add android), le
correctif est rejoué à chaque fois. Idempotent.
"""
import pathlib
import re
import sys

fichiers = list(pathlib.Path("android/app/src/main/java").rglob("MainActivity.java"))
if not fichiers:
    print("MainActivity.java introuvable : correctif des cookies NON appliqué.")
    sys.exit(0)

for chemin in fichiers:
    source = chemin.read_text()

    if "CookieManager.getInstance().flush()" in source:
        print(f"{chemin} : correctif déjà présent.")
        continue

    if "import android.webkit.CookieManager;" not in source:
        source = re.sub(
            r"(import com\.getcapacitor\.BridgeActivity;)",
            "import android.webkit.CookieManager;\n\\1",
            source,
            count=1,
        )

    methode = (
        "    @Override\n"
        "    public void onPause() {\n"
        "        super.onPause();\n"
        "        // Cookies de connexion écrits sur le disque avant une éventuelle\n"
        "        // fermeture de l'appli (sinon : déconnexion au lancement suivant).\n"
        "        CookieManager.getInstance().flush();\n"
        "    }\n"
    )

    # Classe vide « extends BridgeActivity {} » (modèle Capacitor) ou déjà
    # garnie : on insère la méthode juste après l'accolade ouvrante.
    nouveau, n = re.subn(
        r"(public class MainActivity extends BridgeActivity\s*\{)\s*\}",
        "\\1\n" + methode + "}",
        source,
        count=1,
    )
    if n == 0:
        nouveau, n = re.subn(
            r"(public class MainActivity extends BridgeActivity\s*\{)",
            "\\1\n" + methode,
            source,
            count=1,
        )

    if n == 0:
        print(f"{chemin} : forme inattendue, correctif des cookies NON appliqué.")
        continue

    chemin.write_text(nouveau)
    print(f"{chemin} : cookies écrits sur le disque à chaque passage en arrière-plan.")
