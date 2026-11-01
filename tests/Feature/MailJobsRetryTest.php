<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Mails : en cas de refus du serveur (code 421), les nouvelles tentatives sont espacées. */
class MailJobsRetryTest extends TestCase
{
    public function test_les_mails_sont_retentes_plus_tard(): void
    {
        foreach (glob(app_path('Jobs/*.php')) as $fichier) {
            if (! str_contains((string) file_get_contents($fichier), 'Mail::')) {
                continue;
            }
            $classe = 'App\\Jobs\\' . basename($fichier, '.php');
            $proprietes = (new \ReflectionClass($classe))->getDefaultProperties();

            $this->assertNotEmpty($proprietes['backoff'] ?? null, $classe . ' doit espacer ses nouvelles tentatives');
        }

        $verification = (new \ReflectionClass(\App\Jobs\SendEmailVerification::class))->getDefaultProperties();
        $this->assertGreaterThanOrEqual(4, $verification['tries']);
    }
}
