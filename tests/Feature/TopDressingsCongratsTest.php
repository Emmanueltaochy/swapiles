<?php

namespace Tests\Feature;

use App\Jobs\SendTopDressingsCongratsEmails;
use Tests\TestCase;

/**
 * E-mail de félicitations au Top des dressings.
 */
class TopDressingsCongratsTest extends TestCase
{
    public function test_le_mail_mentionne_le_rang_le_top_et_le_lien(): void
    {
        [$subject, $body] = SendTopDressingsCongratsEmails::buildEmail(
            'Marie', 1, 10, 'https://swapiles.com/meilleurs-dressings'
        );

        $this->assertStringContainsString('Marie', $subject);
        $this->assertStringContainsString('Top 10', $subject);
        $this->assertStringContainsString('n°1', $body);
        $this->assertStringContainsString('Top 10', $body);
        $this->assertStringContainsString('https://swapiles.com/meilleurs-dressings', $body);
    }
}
