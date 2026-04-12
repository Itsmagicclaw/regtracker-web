<?php

namespace App\Mail;

use App\Models\MtoProfile;
use App\Models\DetectedChange;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CriticalAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MtoProfile $mto,
        public DetectedChange $change,
    ) {
    }

    public function envelope(): Envelope
    {
        $sourceName = $this->change->regulatorySource->name;
        $severity = strtoupper($this->change->severity);

        return new Envelope(
            subject: "[{$severity}] RegTracker Alert - {$sourceName} Update",
        );
    }

    public function content(): Content
    {
        $entry = json_decode($this->change->raw_data, true);
        $entityName = $entry['name'] ?? $entry['title'] ?? $entry['firmName'] ?? 'Unknown Entity';

        return new Content(
            view: 'mail.critical-alert',
            with: [
                'mto' => $this->mto,
                'change' => $this->change,
                'entityName' => $entityName,
                'changeType' => ucfirst($this->change->change_type),
                'severity' => strtoupper($this->change->severity),
                'source' => $this->change->regulatorySource,
                'summary' => $this->change->summary,
            ],
        );
    }
}
