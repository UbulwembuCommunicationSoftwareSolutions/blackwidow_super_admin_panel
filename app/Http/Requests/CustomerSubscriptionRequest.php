<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerSubscriptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'url' => ['required'],
            'subscription_type_id' => ['required', 'exists:subscription_types'],
            'logo_1' => ['required'],
            'logo_2' => ['required'],
            'logo_3' => ['required'],
            'logo_4' => ['required'],
            'logo_5' => ['required'],
            'customer_id' => ['required', 'exists:customers'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
