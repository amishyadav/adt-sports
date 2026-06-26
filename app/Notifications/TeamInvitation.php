<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin invites a new team member. Reuses the password-reset token
 * so the recipient sets their own password via the existing reset form — the
 * admin never knows or handles the password. Queued so the invite email sends
 * out-of-band (QUEUE_CONNECTION=database + a worker).
 */
class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $inviterName,
        public string $role,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $roleLabel = $this->role === 'admin' ? 'an administrator' : 'a contributor';

        return (new MailMessage)
            ->subject("You've been invited to ADT Sports")
            ->greeting('Welcome to ADT Sports!')
            ->line($this->inviterName . ' has invited you to join the ADT Sports team as ' . $roleLabel . '.')
            ->line('Click the button below to set your password and activate your account.')
            ->action('Set your password', $url)
            ->line('This link expires in 60 minutes. If it expires, use "Forgot password?" on the admin login page to get a fresh one.');
    }
}
