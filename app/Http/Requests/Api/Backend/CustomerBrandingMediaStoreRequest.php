<?php

namespace App\Http\Requests\Api\Backend;

use App\Support\BrandingSync\BrandingSyncPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerBrandingMediaStoreRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:10240'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'slot' => ['sometimes', 'nullable', 'string', Rule::in(BrandingSyncPayload::SLOTS)],
        ];
    }
}
