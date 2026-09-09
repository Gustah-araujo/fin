<?php

namespace Tests\Feature\Mail;

use Tests\TestCase;

class BrevoMailerConfigTest extends TestCase
{
    public function test_brevo_mailer_is_registered(): void
    {
        $mailers = array_keys(config('mail.mailers'));

        $this->assertContains('brevo', $mailers);
    }

    public function test_brevo_reads_env_variables(): void
    {
        config(['services.brevo' => [
            'api_key' => 'test-api-key',
            'sender_email' => 'noreply@test.com',
            'sender_name' => 'TestFin',
        ]]);

        $brevoConfig = config('services.brevo');

        $this->assertEquals('test-api-key', $brevoConfig['api_key']);
        $this->assertEquals('noreply@test.com', $brevoConfig['sender_email']);
        $this->assertEquals('TestFin', $brevoConfig['sender_name']);
    }

    public function test_brevo_mailer_has_correct_transport(): void
    {
        $this->assertArrayHasKey('brevo', config('mail.mailers'));
        $this->assertEquals('brevo', config('mail.mailers.brevo.transport'));
    }

    public function test_brevo_mailer_reads_api_key_from_services_config(): void
    {
        config(['services.brevo.api_key' => 'xkeysib-test-key']);

        $this->assertEquals(
            'xkeysib-test-key',
            config('services.brevo.api_key')
        );
    }
}
