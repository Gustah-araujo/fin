<?php

namespace Tests\Feature\Mail;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\TestCase;

class BrevoTransportTest extends TestCase
{
    public function test_brevo_transport_is_registered_and_resolves(): void
    {
        config(['mail.mailers.brevo.api_key' => 'test-api-key']);

        $transport = Mail::mailer('brevo')->getSymfonyTransport();

        $this->assertInstanceOf(BrevoApiTransport::class, $transport);
    }

    public function test_default_mailer_still_works(): void
    {
        $transport = Mail::mailer(config('mail.default'))->getSymfonyTransport();

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }
}
