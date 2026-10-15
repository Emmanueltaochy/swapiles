#!/usr/bin/env python3
"""Vente en direct : autorise la caméra et le micro dans l'appli Android.

Le live est une page web affichée par l'appli ; le WebView de Capacitor ne
peut donner la caméra et le micro à cette page que si l'appli les déclare
dans son AndroidManifest (Android demande ensuite l'accord du membre, au
moment où il lance son live). Le projet Android étant régénéré à chaque
build (npx cap add android), le correctif est rejoué à chaque fois.
Idempotent.
"""
import pathlib
import sys

manifeste = pathlib.Path("android/app/src/main/AndroidManifest.xml")
if not manifeste.exists():
    print("AndroidManifest.xml introuvable : permissions du live NON ajoutées.")
    sys.exit(0)

source = manifeste.read_text()
permissions = [
    "android.permission.CAMERA",
    "android.permission.RECORD_AUDIO",
    "android.permission.MODIFY_AUDIO_SETTINGS",
]

ajouts = "".join(
    f'    <uses-permission android:name="{p}" />\n' for p in permissions if p not in source
)

if not ajouts:
    print("Permissions du live déjà présentes.")
    sys.exit(0)

source = source.replace("</manifest>", ajouts + "</manifest>", 1)
manifeste.write_text(source)
print("Permissions du live ajoutées (caméra, micro).")
