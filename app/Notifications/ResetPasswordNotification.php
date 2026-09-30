<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(
        public string $resetUrl,
        public int $expiresInMinutes,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = (string) config('app.name');

        return (new MailMessage)
            ->subject("Reset your {$appName} password")
            ->greeting('Hello!')
            ->line('We received a request to reset the password for your '.$appName.' account.')
            ->action('Reset password', $this->resetUrl)
            ->line("This link expires in {$this->expiresInMinutes} minutes.")
            ->line('If you did not request a password reset, no further action is required.');
    }
}
