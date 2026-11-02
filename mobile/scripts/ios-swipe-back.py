#!/usr/bin/env python3
"""iPhone : retour à la page précédente en glissant le doigt depuis le bord gauche.

Safari le fait, mais la vue web de Capacitor ne l'active pas par défaut. On
remplace le contrôleur de Capacitor par une petite sous-classe qui allume
« allowsBackForwardNavigationGestures » (déclarée dans AppDelegate.swift, déjà
compilé dans la cible App : pas de fichier à ajouter au projet Xcode), puis on
la branche dans Main.storyboard. Le projet iOS étant régénéré à chaque build,
le correctif est rejoué chaque fois. Idempotent.
"""
import pathlib
import re

APP = pathlib.Path("ios/App/App")
delegue = APP / "AppDelegate.swift"
storyboard = APP / "Base.lproj" / "Main.storyboard"

if not delegue.exists() or not storyboard.exists():
    print("Projet iOS introuvable : retour par glissement NON activé.")
    raise SystemExit(0)

CLASSE = '''

// Retour à la page précédente en glissant depuis le bord gauche, comme dans Safari.
class SwapilesViewController: CAPBridgeViewController {
    override func capacitorDidLoad() {
        super.capacitorDidLoad()
        webView?.allowsBackForwardNavigationGestures = true
    }
}
'''

source = delegue.read_text()
if "class SwapilesViewController" not in source:
    if "import Capacitor" not in source:
        source = "import Capacitor\n" + source
    delegue.write_text(source.rstrip() + CLASSE)
    print("AppDelegate : contrôleur avec retour par glissement ajouté.")

xml = storyboard.read_text()
nouveau, n = re.subn(
    r'customClass="CAPBridgeViewController" customModule="Capacitor"',
    'customClass="SwapilesViewController" customModule="App" customModuleProvider="target"',
    xml,
)
if n:
    storyboard.write_text(nouveau)
    print("Main.storyboard : contrôleur Swap'Îles branché (retour par glissement).")
elif "SwapilesViewController" in xml:
    print("Main.storyboard : déjà branché.")
else:
    print("Main.storyboard : contrôleur Capacitor introuvable, retour par glissement NON activé.")
