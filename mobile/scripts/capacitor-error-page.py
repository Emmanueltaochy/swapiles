#!/usr/bin/env python3
"""N'affiche « Connexion impossible » que s'il n'y a VRAIMENT pas de connexion.

Capacitor affiche la page d'erreur (www/error.html) dès qu'un chargement de
page n'aboutit pas, sans regarder pourquoi :

  iOS      un chargement simplement INTERROMPU (une autre page demandée
           avant la fin, une redirection, l'ouverture de l'appli pendant que
           la page se charge…) est traité comme une panne. L'écran
           « Connexion impossible » apparaissait alors que tout marchait.
  Android  une page du site qui répond par une erreur (page introuvable,
           formulaire expiré…) affichait aussi « Connexion impossible » au
           lieu de la page d'erreur du site, qui explique quoi faire.

Ce correctif modifie la copie de Capacitor installée par npm (compilée telle
quelle par Xcode et Gradle) :
  - iOS : on ignore les interruptions (NSURLErrorCancelled -999 et
    WebKitErrorFrameLoadInterruptedByPolicyChange 102) ;
  - Android : une réponse d'erreur du site n'ouvre plus la page d'erreur ;
    seules les vraies pannes de réseau le font.

Usage (depuis mobile/, après npm install) :
  python3 scripts/capacitor-error-page.py [ios|android]   (les deux par défaut)

Idempotent : sans effet si le correctif est déjà posé.
"""
import pathlib
import sys

MARQUE = "SWAPILES: interruption, pas une panne"

IOS = pathlib.Path("node_modules/@capacitor/ios/Capacitor/Capacitor/WebViewDelegationHandler.swift")
ANDROID = pathlib.Path(
    "node_modules/@capacitor/android/capacitor/src/main/java/com/getcapacitor/BridgeWebViewClient.java"
)

GARDE_IOS = (
    "        // " + MARQUE + " : une page demandée avant la fin de la\n"
    "        // précédente, ou une redirection, n'est pas une absence de réseau.\n"
    "        let erreurNs = error as NSError\n"
    "        if (erreurNs.domain == NSURLErrorDomain && erreurNs.code == NSURLErrorCancelled)\n"
    "            || (erreurNs.domain == \"WebKitErrorDomain\" && erreurNs.code == 102) {\n"
    "            return\n"
    "        }\n"
    "\n"
)


def corriger_ios() -> bool:
    if not IOS.exists():
        print(f"iOS : {IOS} introuvable, correctif NON appliqué.")
        return False

    source = IOS.read_text()
    if MARQUE in source:
        print("iOS : correctif déjà en place.")
        return True

    entetes = [
        "open func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {\n",
        "open func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {\n",
    ]
    for entete in entetes:
        if source.count(entete) != 1:
            print("iOS : forme inattendue de Capacitor, correctif NON appliqué.")
            return False
        source = source.replace(entete, entete + GARDE_IOS)

    IOS.write_text(source)
    print("iOS : un chargement interrompu n'affiche plus « Connexion impossible ».")
    return True


def corriger_android() -> bool:
    if not ANDROID.exists():
        print(f"Android : {ANDROID} introuvable, correctif NON appliqué.")
        return False

    source = ANDROID.read_text()
    if MARQUE in source:
        print("Android : correctif déjà en place.")
        return True

    debut = source.find("public void onReceivedHttpError(")
    if debut == -1:
        print("Android : forme inattendue de Capacitor, correctif NON appliqué.")
        return False

    ancien = "        if (errorPath != null && request.isForMainFrame()) {\n            view.loadUrl(errorPath);\n        }\n"
    position = source.find(ancien, debut)
    if position == -1:
        print("Android : forme inattendue de Capacitor, correctif NON appliqué.")
        return False

    nouveau = (
        "        // " + MARQUE + " : le site a répondu (page introuvable,\n"
        "        // formulaire expiré…) ; on affiche SA page, qui explique quoi faire.\n"
    )
    source = source[:position] + nouveau + source[position + len(ancien):]

    ANDROID.write_text(source)
    print("Android : une erreur du site n'affiche plus « Connexion impossible ».")
    return True


if __name__ == "__main__":
    cibles = sys.argv[1:] or ["ios", "android"]
    if "ios" in cibles:
        corriger_ios()
    if "android" in cibles:
        corriger_android()
