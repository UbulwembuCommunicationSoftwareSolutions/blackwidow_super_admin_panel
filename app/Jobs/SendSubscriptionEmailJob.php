<?php

namespace App\Jobs;

use Mail;
use App\Mail\AppURLMail;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Services\CMSService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSubscriptionEmailJob implements ShouldQueue
{
    use Queueable;

    public $customerUser;

    public $customerSubscription;
    /**
     * Create a new job instance.
     */
    public function __construct(CustomerUser $customerUser,CustomerSubscription $customerSubscription)
    {
        $this->customerUser = $customerUser;
        $this->customerSubscription = $customerSubscription;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = $this->customerUser; // Replace with actual user data
        $customer = $this->customerUser->customer; // Replace with actual customer data
        $app_name = $this->customerSubscription->app_name;
        $cellphone = $this->customerUser->cellphone;
        $app_install_link = $this->customerSubscription->url;
        $email = $this->customerUser->email_address;
        Mail::to($this->customerUser->email_address)->send(
            new AppURLMail($user, $customer, $app_name, $cellphone, $app_install_link,$email)
        );
    }
}
