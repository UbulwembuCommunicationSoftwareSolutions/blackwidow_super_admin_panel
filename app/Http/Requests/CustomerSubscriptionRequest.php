<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerSubscriptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'app_url' => ['required'],
            'customer_id' => ['required', 'exists:customers'],
            'console_login_logo' => ['required'],
            'console_menu_logo' => ['required'],
            'console_background_logo' => ['required'],
            'app_install_logo' => ['required'],
            'app_background_logo' => ['required'],
            'subscription_type_id' => ['required', 'exists:subscription_types'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
