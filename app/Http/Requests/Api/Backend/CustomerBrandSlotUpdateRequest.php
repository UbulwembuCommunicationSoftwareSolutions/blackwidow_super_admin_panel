<?php

namespace App\Http\Requests\Api\Backend;

use Illuminate\Foundation\Http\FormRequest;

class CustomerBrandSlotUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_branding_media_id' => ['nullable', 'integer', 'exists:customer_branding_media,id'],
        ];
    }
}
