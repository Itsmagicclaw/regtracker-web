<?php

namespace App\Mail;

use App\Models\MtoProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyDigest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MtoProfile $mto,
        public array $changes,
    ) {}

    public function envelope(): Envelope
    {
        $date  = now()->format('d M Y');
        $count = count($this->changes);
        return new Envelope(
            subject: "RegTracker Daily Digest — {$count} update(s) — {$date}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.daily-digest');
    }

    public function attachments(): array
    {
        return [];
    }
}
