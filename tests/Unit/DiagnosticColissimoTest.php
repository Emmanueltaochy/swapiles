<?php

namespace Tests\Unit;

use App\Console\Commands\DiagnosticColissimo;
use PHPUnit\Framework\TestCase;

class DiagnosticColissimoTest extends TestCase
{
    public function test_raison_colissimo_sans_donnees_personnelles(): void
    {
        $msg = 'Erreur Colissimo HTTP 400 : {"messages":[{"id":"30221","type":"ERROR","messageContent":"Le numéro de portable du destinataire 0692123456 est incorrect"},{"id":"0","messageContent":"OK"}],"labelV2Response":null}';
        $this->assertSame('Colissimo : 30221 Le numéro de portable du destinataire [numéro] est incorrect', DiagnosticColissimo::raison($msg));
        $this->assertSame("Adresse de livraison de l'acheteur manquante sur cette commande.", DiagnosticColissimo::raison("Adresse de livraison de l'acheteur manquante sur cette commande."));
        $this->assertSame('Erreur Colissimo HTTP 500', DiagnosticColissimo::raison('Erreur Colissimo HTTP 500 : {"x":"marie@ex.com"}'));
    }
}
