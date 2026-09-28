<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrossAppHandoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.required' => 'Choose the app to open.',
            'to.max' => 'That app link is too long.',
        ];
    }
}
