<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Anmeldecode per E-Mail (docs/konzept.md Abschnitt 25): sechs Ziffern, 15 Minuten gueltig.
 */
class LoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Dein Anmeldecode: '.$this->code);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.login-code');
    }
}
