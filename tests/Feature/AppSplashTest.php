<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Écran de démarrage de l'appli : le palmier en plein écran, en mode clair
 * COMME en mode sombre.
 *
 * L'outil qui fabrique les images (@capacitor/assets) crée aussi une version
 * « mode sombre ». Sans image prévue pour ce mode, il la fabrique lui-même à
 * partir de l'icône : un petit carré au milieu d'un fond gris foncé (#111111).
 * C'est ce que voyaient tous les iPhone réglés en mode sombre au lancement.
 */
class AppSplashTest extends TestCase
{
    public function test_le_mode_sombre_a_sa_propre_image_de_demarrage(): void
    {
        $clair = base_path('mobile/resources/splash.png');
        $sombre = base_path('mobile/resources/splash-dark.png');

        $this->assertFileExists($sombre, 'Sans splash-dark.png, le mode sombre affiche un petit carré sur fond gris.');
        // Même image : l'écran de démarrage est identique dans les deux modes.
        $this->assertSame(md5_file($clair), md5_file($sombre));
    }

    public function test_la_nouvelle_soumission_porte_un_nouveau_numero_de_version(): void
    {
        // La 1.0 est déjà validée par Apple : toute nouvelle soumission doit
        // porter un numéro supérieur.
        $this->assertMatchesRegularExpression('/MARKETING_VERSION: "1\.0\.[1-9]\d*"|MARKETING_VERSION: "1\.[1-9]/', file_get_contents(base_path('codemagic.yaml')));
    }
}
