<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** API-only mail: pre-rendered HTML deliberately does not invoke Blade composers. */
final class MobileAccountMail extends Mailable
{
    public function __construct(public readonly array $data, public readonly bool $activation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->activation
            ? 'Verification Email - Friendie Logistics' : 'Reset Password Link - Friendie Logistics');
    }

    public function content(): Content
    {
        $url = e($this->data[$this->activation ? 'activation_url' : 'reset_url']);
        $label = $this->activation ? 'Verify your email' : 'Reset your password';
        $expiry = $this->activation ? '24 hours' : config('auth.passwords.users.expire', 60).' minutes';

        return new Content(htmlString: '<!doctype html><html><body><h1>Friendie Logistics</h1><p>Hello '
            .e($this->data['user_name']).',</p><p><a href="'.$url.'">'.$label.'</a></p><p>This link expires in '
            .$expiry.'. If you did not request this, ignore this email.</p></body></html>');
    }
}
