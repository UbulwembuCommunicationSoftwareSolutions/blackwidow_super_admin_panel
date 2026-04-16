<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnvVariablesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'key' => ['required'],
            'value' => ['nullable', 'string'],
            'customer_subscription_id' => ['required', 'exists:customer_subscriptions,id'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
