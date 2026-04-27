<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BroadcastCampaignMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public array $payload,
    ) {
    }

    public function build(): self
    {
        return $this->subject((string) ($this->payload['subject'] ?? 'Broadcast Message'))
            ->view('mail.broadcast-campaign')
            ->with([
                'title' => (string) ($this->payload['title'] ?? ''),
                'messageBody' => (string) ($this->payload['message'] ?? ''),
                'type' => (string) ($this->payload['type'] ?? 'info'),
                'ctaLabel' => $this->payload['cta_label'] ?? null,
                'ctaUrl' => $this->payload['cta_url'] ?? null,
            ]);
    }
}
