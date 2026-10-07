#!/usr/bin/env python3
"""Rend les notifications possibles dans l'appli iOS.

Le modèle d'appli de Capacitor ne contient RIEN pour les notifications :
  1. AppDelegate ne transmet pas le jeton de l'iPhone au plug-in
     PushNotifications : l'enregistrement ne se termine jamais ;
  2. aucune autorisation « aps-environment » : iOS refuse d'attribuer une
     adresse de notification à l'appli.
Sans ces deux pièces, aucun iPhone ne reçoit de notification, même avec la
clé Apple installée sur le serveur.

Usage (depuis mobile/) :
  python3 scripts/ios-push.py            pose les deux pièces
  python3 scripts/ios-push.py --retirer  retire l'autorisation (profil Apple
                                         qui ne la contient pas encore : le
                                         build continue, sans notifications)

Le projet iOS étant régénéré à chaque build, le correctif est rejoué chaque
fois. Idempotent.
"""
import pathlib
import plistlib
import re
import sys

APP = pathlib.Path("ios/App/App")
PROJET = pathlib.Path("ios/App/App.xcodeproj/project.pbxproj")
AUTORISATIONS = APP / "App.entitlements"
LIGNE = "CODE_SIGN_ENTITLEMENTS = App/App.entitlements;"


def retirer() -> None:
    if PROJET.exists():
        source = PROJET.read_text()
        PROJET.write_text(re.sub(r"\n\s*" + re.escape(LIGNE), "", source))
    print("Autorisation de notifications retirée : ce build n'aura PAS de notifications.")


def poser() -> None:
    if not APP.exists() or not PROJET.exists():
        print("Projet iOS introuvable : correctif des notifications NON appliqué.")
        return

    # 1. AppDelegate : transmettre le jeton (ou l'échec) au plug-in.
    delegue = APP / "AppDelegate.swift"
    source = delegue.read_text()
    if "capacitorDidRegisterForRemoteNotifications" not in source:
        methodes = (
            "\n"
            "    // Notifications : iOS remet ici le jeton de l'iPhone ; on le\n"
            "    // transmet au plug-in PushNotifications (sinon l'enregistrement\n"
            "    // ne se termine jamais et aucune notification n'arrive).\n"
            "    func application(_ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {\n"
            "        NotificationCenter.default.post(name: .capacitorDidRegisterForRemoteNotifications, object: deviceToken)\n"
            "    }\n"
            "\n"
            "    func application(_ application: UIApplication, didFailToRegisterForRemoteNotificationsWithError error: Error) {\n"
            "        NotificationCenter.default.post(name: .capacitorDidFailToRegisterForRemoteNotifications, object: error)\n"
            "    }\n"
        )
        fin = source.rstrip().rfind("}")
        if fin == -1:
            print("AppDelegate.swift : forme inattendue, correctif NON appliqué.")
            return
        delegue.write_text(source[:fin].rstrip("\n") + "\n" + methodes + "\n}\n")
        print("AppDelegate : jeton de notification transmis au plug-in.")
    else:
        print("AppDelegate : déjà en place.")

    # 2. Autorisation « aps-environment » (production : TestFlight et App Store).
    donnees = {}
    if AUTORISATIONS.exists():
        with AUTORISATIONS.open("rb") as f:
            donnees = plistlib.load(f)
    donnees["aps-environment"] = "production"
    with AUTORISATIONS.open("wb") as f:
        plistlib.dump(donnees, f)

    # 3. Le projet doit pointer vers ce fichier, pour la cible App.
    source = PROJET.read_text()
    if LIGNE not in source:
        source, n = re.subn(
            r"(\n(\s*)INFOPLIST_FILE = App/Info\.plist;)",
            "\\1\n\\2" + LIGNE,
            source,
        )
        if n == 0:
            print("project.pbxproj : forme inattendue, autorisation NON branchée.")
            return
        PROJET.write_text(source)
    print("Autorisation de notifications posée (aps-environment = production).")


if __name__ == "__main__":
    retirer() if "--retirer" in sys.argv else poser()
