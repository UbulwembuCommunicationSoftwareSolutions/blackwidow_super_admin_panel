<?php

namespace App\Http\Requests\Api\Backend;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerSubscriptionBrandSlotUpdateRequest extends FormRequest
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
            'action' => ['required', 'string', Rule::in(['inherit', 'clear', 'assign', 'upload'])],
            'customer_branding_media_id' => [
                Rule::requiredIf(fn () => $this->input('action') === 'assign'),
                'nullable',
                'integer',
                'exists:customer_branding_media,id',
            ],
            'file' => [
                Rule::requiredIf(fn () => $this->input('action') === 'upload'),
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,gif,webp,svg',
                'max:10240',
            ],
        ];
    }
}
