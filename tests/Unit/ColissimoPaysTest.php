<?php

namespace Tests\Unit;

use App\Support\ColissimoPays;
use Tests\TestCase;

class ColissimoPaysTest extends TestCase
{
    public function test_code_pays_colissimo(): void
    {
        $this->assertSame('FR', ColissimoPays::code('France', '13001'));      // saisi à la commande
        $this->assertSame('RE', ColissimoPays::code('France', '97410'));      // le code postal fait foi
        $this->assertSame('GP', ColissimoPays::code('FR', '97110'));
        $this->assertSame('MQ', ColissimoPays::code(null, '97200'));
        $this->assertSame('GF', ColissimoPays::code('France', '97300'));
        $this->assertSame('YT', ColissimoPays::code('France', '97600'));
        $this->assertSame('BL', ColissimoPays::code('France', '97133'));
        $this->assertSame('MF', ColissimoPays::code('France', '97150'));
        $this->assertSame('RE', ColissimoPays::code('La Réunion', ''));
        $this->assertSame('BE', ColissimoPays::code('Belgique', '1000'));
        $this->assertSame('FR', ColissimoPays::code('', ''));
    }
}
