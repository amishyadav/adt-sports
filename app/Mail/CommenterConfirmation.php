<?php

namespace App\Mail;

use App\Support\SubscribeThrottle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CommenterConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $confirmUrl,
        public string $siteName,
        public string $email = '',
    ) {}

    /**
     * If the send fails (sync re-throws to the controller; a queue worker calls
     * this), clear the burst limiter so the visitor isn't locked out with no
     * email, and surface the failure.
     */
    public function failed(Throwable $e): void
    {
        report($e);

        if ($this->email !== '') {
            SubscribeThrottle::clearBurst($this->email);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your email to comment on ' . $this->siteName);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.commenter-confirmation');
    }
}
