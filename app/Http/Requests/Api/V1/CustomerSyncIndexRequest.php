<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CustomerSyncIndexRequest extends FormRequest
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
            'app_url' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->query->has('app_url') && ! $this->request->has('app_url')) {
            $this->merge([
                'app_url' => $this->query('app_url'),
            ]);
        }
    }
}
