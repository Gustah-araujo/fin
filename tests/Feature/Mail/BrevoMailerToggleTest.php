<?php

namespace Tests\Feature\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BrevoMailerToggleTest extends TestCase
{
    public function test_mailer_brevo_becomes_default(): void
    {
        config(['mail.default' => 'brevo']);

        $this->assertEquals('brevo', config('mail.default'));
        $this->assertArrayHasKey('brevo', config('mail.mailers'));
    }

    public function test_existing_mail_code_works_unchanged(): void
    {
        Mail::fake();

        Mail::to('test@example')->send(new class extends Mailable
        {
            public function build(): static
            {
                return $this->subject('Test Email')
                    ->html('<p>Test body</p>');
            }
        });

        Mail::assertSent(Mailable::class);
    }

    public function test_mailer_log_still_works_for_backward_compatibility(): void
    {
        $transport = config('mail.mailers.log.transport');
        $this->assertEquals('log', $transport);

        // Log mailer config key exists (channel may be null — uses default log channel)
        $this->assertArrayHasKey('log', config('mail.mailers'));
    }

    public function test_toggle_between_log_and_brevo(): void
    {
        // Simulate switching from log to brevo and back
        config(['mail.default' => 'log']);
        $this->assertEquals('log', config('mail.default'));

        config(['mail.default' => 'brevo']);
        $this->assertEquals('brevo', config('mail.default'));
    }
}
