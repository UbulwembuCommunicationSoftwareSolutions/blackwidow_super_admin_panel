<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppURLMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $customer;
    public $app_name;
    public $cellphone;
    public $app_install_link;
    /**
     * Create a new message instance.
     *
     * @param array $data
     */
    public function __construct($user, $customer, $app_name, $cellphone, $app_install_link)
    {
        $this->user = $user;
        $this->customer = $customer;
        $this->app_name = $app_name;
        $this->cellphone = $cellphone;
        $this->app_install_link = $app_install_link;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject("Welcome to {$this->app_name}")
            ->view('emails.app-login');
    }
}
