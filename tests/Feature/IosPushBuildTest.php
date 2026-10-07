<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Notifications iPhone : ce que le build iOS doit contenir.
 *
 * Le modèle d'appli de Capacitor ne contient rien pour les notifications :
 * ni le relais du jeton de l'iPhone (AppDelegate), ni l'autorisation
 * « aps-environment ». Aucun iPhone ne pouvait donc en recevoir, même avec la
 * clé Apple sur le serveur. Ces tests gardent le branchement du correctif.
 */
class IosPushBuildTest extends TestCase
{
    private function codemagic(): string
    {
        return file_get_contents(base_path('codemagic.yaml'));
    }

    public function test_le_correctif_est_applique_au_build_ios(): void
    {
        $this->assertFileExists(base_path('mobile/scripts/ios-push.py'));
        $this->assertStringContainsString('python3 scripts/ios-push.py', $this->codemagic());
    }

    public function test_les_notifications_sont_activees_chez_apple_avant_la_signature(): void
    {
        $yaml = $this->codemagic();

        $activation = strpos($yaml, '--capability PUSH_NOTIFICATIONS');
        $signature = strpos($yaml, 'app-store-connect fetch-signing-files');

        $this->assertNotFalse($activation);
        $this->assertLessThan($signature, $activation, 'Les notifications doivent être activées avant de créer le profil.');
    }

    public function test_un_profil_sans_notifications_ne_casse_pas_le_build(): void
    {
        // Sans ce repli, le build échouerait à la signature tant que les
        // notifications ne sont pas autorisées chez Apple.
        $this->assertStringContainsString('python3 scripts/ios-push.py --retirer', $this->codemagic());
    }

    public function test_le_script_pose_le_jeton_et_l_autorisation(): void
    {
        $script = file_get_contents(base_path('mobile/scripts/ios-push.py'));

        $this->assertStringContainsString('capacitorDidRegisterForRemoteNotifications', $script);
        $this->assertStringContainsString('capacitorDidFailToRegisterForRemoteNotifications', $script);
        $this->assertStringContainsString('"aps-environment"', $script);
        $this->assertStringContainsString('"production"', $script);
    }

    public function test_la_cle_apple_peut_etre_collee_telle_quelle(): void
    {
        $deploy = file_get_contents(base_path('.github/workflows/deploy.yml'));

        $this->assertStringContainsString('APNS_KEY_P8: ${{ secrets.APNS_KEY_P8 }}', $deploy);
        $this->assertStringContainsString('printf \'%s\n\' "$APNS_KEY_P8"', $deploy);
    }

    public function test_testflight_utilise_les_serveurs_apple_de_production(): void
    {
        // TestFlight et l'App Store passent par les serveurs de production.
        $this->assertTrue((bool) config('push.apns.production'));
    }
}
