#!/usr/bin/env python3
"""Force compileSdk/targetSdk à 36 (exigence Google Play) dans le projet Android
Capacitor régénéré à chaque build. Idempotent."""
import re
import pathlib

TARGET = 36

# Capacitor place les versions de SDK dans variables.gradle.
candidates = [
    pathlib.Path("android/variables.gradle"),
    pathlib.Path("android/app/build.gradle"),
    pathlib.Path("android/build.gradle"),
]

patched = False
for p in candidates:
    if not p.exists():
        continue
    g = p.read_text()
    before = g
    g = re.sub(r"compileSdkVersion\s*=?\s*\d+", f"compileSdkVersion = {TARGET}", g)
    g = re.sub(r"targetSdkVersion\s*=?\s*\d+", f"targetSdkVersion = {TARGET}", g)
    # Forme "compileSdk 35" / "targetSdk 35" (sans "Version")
    g = re.sub(r"compileSdk\s+\d+", f"compileSdk {TARGET}", g)
    g = re.sub(r"targetSdk\s+\d+", f"targetSdk {TARGET}", g)
    if g != before:
        p.write_text(g)
        patched = True
        print(f"SDK cible 36 appliqué dans {p}")

if not patched:
    print("Aucun fichier de SDK trouvé à patcher (à vérifier).")
