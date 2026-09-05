#!/usr/bin/env python3
"""Force le fond de l'écran de lancement iOS à la couleur du splash (#10665E).

Par défaut, le storyboard généré par Capacitor utilise la couleur système :
elle devient NOIRE en mode sombre, ce qui fait apparaître le splash comme un
petit carré teal flottant sur du noir. En alignant le fond du storyboard sur
la couleur de fond de l'image, l'écran paraît uniforme.
Idempotent : le projet iOS étant régénéré à chaque build, on rejoue ce patch.
"""
import re
import pathlib

# Couleur de fond exacte de mobile/resources/splash.png : RGB(16, 102, 94)
TEAL = '<color key="backgroundColor" red="0.062745098" green="0.4" blue="0.368627451" alpha="1" colorSpace="custom" customColorSpace="sRGB"/>'

path = pathlib.Path("ios/App/App/Base.lproj/LaunchScreen.storyboard")
if not path.exists():
    print(f"{path} introuvable — patch ignoré.")
    raise SystemExit(0)

xml = path.read_text()
patched, count = re.subn(r'<color key="backgroundColor"[^>]*/>', TEAL, xml)

if count == 0:
    print("Aucune couleur de fond trouvée dans le storyboard.")
else:
    path.write_text(patched)
    print(f"Fond de l'écran de lancement forcé au teal #10665E ({count} occurrence(s)).")
