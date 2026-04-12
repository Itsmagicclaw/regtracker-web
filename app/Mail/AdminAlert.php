<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $alertSubject,
        public string $alertMessage,
        public string $severity = 'error',
    ) {}

    public function envelope(): Envelope
    {
        $emoji = match($this->severity) {
            'critical' => '🔴',
            'warning'  => '⚠️',
            default    => '❌',
        };
        return new Envelope(subject: "{$emoji} RegTracker Admin Alert — {$this->alertSubject}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-alert');
    }

    public function attachments(): array
    {
        return [];
    }
}
