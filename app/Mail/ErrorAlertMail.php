<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Fehlermeldung an die Admins (App\Support\ErrorAlert, docs/konzept.md Abschnitt 27), reiner Text.
 */
class ErrorAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $title, public string $body) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Kunstbegleiter: Fehler '.$this->title);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.error-alert');
    }
}
