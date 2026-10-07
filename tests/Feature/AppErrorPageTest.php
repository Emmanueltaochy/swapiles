<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * « Connexion impossible » ne doit apparaître que sans réseau.
 *
 * Capacitor ouvrait la page d'erreur pour un simple chargement interrompu
 * (iOS) ou pour une page d'erreur du site (Android) : l'écran s'affichait à
 * l'ouverture de l'appli alors que la connexion marchait.
 */
class AppErrorPageTest extends TestCase
{
    private string $dossier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dossier = sys_get_temp_dir() . '/swp-capacitor-' . uniqid();

        // Les deux fichiers de Capacitor, réduits aux passages concernés
        // (même forme que Capacitor 7).
        $ios = $this->dossier . '/node_modules/@capacitor/ios/Capacitor/Capacitor';
        $android = $this->dossier . '/node_modules/@capacitor/android/capacitor/src/main/java/com/getcapacitor';
        mkdir($ios, 0777, true);
        mkdir($android, 0777, true);

        file_put_contents($ios . '/WebViewDelegationHandler.swift', <<<'SWIFT'
            open func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
                if let errorURL = bridge?.config.errorPathURL {
                    webView.load(URLRequest(url: errorURL))
                }
            }

            open func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
                if let errorURL = bridge?.config.errorPathURL {
                    webView.load(URLRequest(url: errorURL))
                }
            }
        SWIFT);

        file_put_contents($android . '/BridgeWebViewClient.java', <<<'JAVA'
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                String errorPath = bridge.getErrorUrl();
                if (errorPath != null && request.isForMainFrame()) {
                    view.loadUrl(errorPath);
                }
            }

            public void onReceivedHttpError(WebView view, WebResourceRequest request, WebResourceResponse errorResponse) {
                String errorPath = bridge.getErrorUrl();
                if (errorPath != null && request.isForMainFrame()) {
                    view.loadUrl(errorPath);
                }
            }
        JAVA);
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->dossier]))->run();
        parent::tearDown();
    }

    private function corriger(): string
    {
        $process = new Process(['python3', base_path('mobile/scripts/capacitor-error-page.py')], $this->dossier);
        $process->mustRun();

        return $process->getOutput();
    }

    public function test_ios_ignore_les_chargements_interrompus(): void
    {
        $this->corriger();

        $swift = file_get_contents($this->dossier . '/node_modules/@capacitor/ios/Capacitor/Capacitor/WebViewDelegationHandler.swift');

        // La garde est posée dans les DEUX cas d'échec, avant la page d'erreur.
        $this->assertSame(2, substr_count($swift, 'erreurNs.code == NSURLErrorCancelled'));
        $this->assertSame(2, substr_count($swift, 'erreurNs.code == 102'));
        $this->assertLessThan(strpos($swift, 'errorPathURL'), strpos($swift, 'NSURLErrorCancelled'));
    }

    public function test_android_garde_la_page_d_erreur_pour_les_vraies_pannes_seulement(): void
    {
        $this->corriger();

        $java = file_get_contents($this->dossier . '/node_modules/@capacitor/android/capacitor/src/main/java/com/getcapacitor/BridgeWebViewClient.java');
        [$reseau, $http] = explode('onReceivedHttpError', $java);

        $this->assertStringContainsString('view.loadUrl(errorPath);', $reseau, 'Panne de réseau : la page d’erreur reste.');
        $this->assertStringNotContainsString('view.loadUrl(errorPath);', $http, 'Erreur du site : on affiche la page du site.');
    }

    public function test_le_correctif_peut_etre_rejoue(): void
    {
        $this->corriger();
        $sortie = $this->corriger();

        $this->assertStringContainsString('déjà en place', $sortie);
        $swift = file_get_contents($this->dossier . '/node_modules/@capacitor/ios/Capacitor/Capacitor/WebViewDelegationHandler.swift');
        $this->assertSame(2, substr_count($swift, 'NSURLErrorCancelled'));
    }

    public function test_le_correctif_est_applique_dans_chaque_build(): void
    {
        $yaml = file_get_contents(base_path('codemagic.yaml'));

        // Android (validation), iOS, Android (AAB signé) : juste après npm install.
        $this->assertSame(3, substr_count($yaml, "npm install\n          # « Connexion impossible » seulement en cas de VRAIE panne de réseau\n          # (pas pour un chargement interrompu ou une erreur du site).\n          python3 scripts/capacitor-error-page.py"));
    }

    public function test_la_page_d_erreur_reessaie_en_silence_avant_d_alarmer(): void
    {
        $html = file_get_contents(base_path('mobile/www/error.html'));

        // Au départ : un simple indicateur de chargement, le message est caché.
        $this->assertMatchesRegularExpression('#<div class="card" id="chargement"#', $html);
        $this->assertMatchesRegularExpression('#<div class="card" id="erreur" hidden>#', $html);
        $this->assertStringContainsString("mode: 'no-cors'", $html);
        $this->assertStringContainsString("window.addEventListener('online', retry)", $html);
        // Pas de boucle infinie si le site répond mais que la page échoue.
        $this->assertStringContainsString('reprisesRecentes() > 2', $html);
    }
}
