#!/usr/bin/env python3
"""Écran de démarrage Android 12+ : fond vert de la marque et palmier blanc seul.

Depuis Android 12, le système affiche lui-même l'icône de l'appli au centre
d'un fond qui suit le thème du téléphone (blanc, ou NOIR en mode sombre) :
on voyait un petit carré vert (l'icône) flottant sur du noir. On fixe le fond
au vert #10665E (celui de l'icône et de l'image de démarrage) et on affiche le palmier
blanc sans son carré. Le projet Android étant régénéré à chaque build, le
correctif est rejoué à chaque fois. Idempotent.
"""
import pathlib
import shutil
import sys

source = pathlib.Path("resources/splash-icone-android.png")
res = pathlib.Path("android/app/src/main/res")
styles = res / "values" / "styles.xml"
if not styles.exists() or not source.exists():
    print("styles.xml ou splash-icone-android.png introuvable : écran de démarrage Android inchangé.")
    sys.exit(0)

dossier = res / "drawable-nodpi"
dossier.mkdir(parents=True, exist_ok=True)
shutil.copy(source, dossier / "splash_palmier.png")

xml = styles.read_text()
ajouts = {
    "windowSplashScreenBackground": "#10665E",
    "windowSplashScreenAnimatedIcon": "@drawable/splash_palmier",
}
debut = xml.find('name="AppTheme.NoActionBarLaunch"')
if debut == -1:
    print("Thème de démarrage introuvable dans styles.xml : inchangé.")
    sys.exit(0)
fin = xml.find("</style>", debut)
bloc = xml[debut:fin]
lignes = "".join(f'        <item name="{k}">{v}</item>\n' for k, v in ajouts.items() if f'name="{k}"' not in bloc)
if lignes:
    xml = xml[:fin] + lignes + "    " + xml[fin:]
    styles.write_text(xml)
    print("Écran de démarrage Android : fond #10665E et palmier blanc.")
else:
    print("Écran de démarrage Android déjà réglé.")
