<?php

namespace App\Mail;

use App\Models\CustomerUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The password reset email for a tenant user.
 *
 * The link is minted by the tenant app, which owns the password_reset_tokens
 * table, and handed to us to send so every system's reset email comes from one
 * place. We never build the URL ourselves.
 */
class CustomerPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CustomerUser $customerUser,
        public string $resetUrl,
        public string $appName,
        public int $expiresInMinutes,
    ) {}

    public function build(): self
    {
        return $this->subject("Set your {$this->appName} password")
            ->view('emails.password-reset', [
                'name' => trim($this->customerUser->first_name.' '.$this->customerUser->last_name),
                'email' => $this->customerUser->email_address,
                'company_name' => $this->customerUser->customer?->company_name,
                'app_name' => $this->appName,
                'reset_url' => $this->resetUrl,
                'expires_in_minutes' => $this->expiresInMinutes,
            ]);
    }
}
