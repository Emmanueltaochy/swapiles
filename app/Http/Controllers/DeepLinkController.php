<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Fichiers de vérification qui permettent aux liens swapiles.com d'ouvrir
 * directement l'application quand elle est installée.
 *
 * Android les lit à l'installation de l'app, Apple au premier lancement.
 * Ils doivent être servis en HTTPS, à la racine, sans redirection.
 */
class DeepLinkController extends Controller
{
    /** Android : /.well-known/assetlinks.json */
    public function android(): JsonResponse|Response
    {
        $empreintes = (array) config('deeplinks.android.sha256');

        // Sans empreinte, mieux vaut ne rien servir : un fichier incomplet
        // fait échouer la vérification au lieu de rester neutre.
        if (empty($empreintes)) {
            return response('', 404);
        }

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => (string) config('deeplinks.android.package'),
                'sha256_cert_fingerprints' => $empreintes,
            ],
        ]]);
    }

    /** iOS : /.well-known/apple-app-site-association (servi en JSON, sans extension) */
    public function apple(): JsonResponse|Response
    {
        $teamId = config('deeplinks.ios.team_id');
        $bundleId = config('deeplinks.ios.bundle_id');

        if (blank($teamId) || blank($bundleId)) {
            return response('', 404);
        }

        return response()->json([
            'applinks' => [
                'apps' => [],
                'details' => [[
                    'appID' => $teamId . '.' . $bundleId,
                    // Tout le site ouvre l'app, sauf l'administration et les
                    // pages qui doivent rester dans un navigateur.
                    'paths' => ['NOT /admin/*', 'NOT /.well-known/*', '*'],
                ]],
            ],
        ]);
    }
}
