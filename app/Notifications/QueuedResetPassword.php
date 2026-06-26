<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The framework's password-reset notification, but queued so the email sends
 * out-of-band (QUEUE_CONNECTION=database + a worker). The branded message set by
 * ResetPassword::toMailUsing() in AppServiceProvider is inherited unchanged.
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
