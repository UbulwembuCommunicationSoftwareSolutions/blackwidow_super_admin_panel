<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnvVariablesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'key' => ['required'],
            'value' => ['required'],
            'customer_subscription_id' => ['required', 'exists:customer_subscriptions'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}