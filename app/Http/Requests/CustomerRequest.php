<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => ['required'],
            'surname' => ['required'],
            'cellphone' => ['required'],
        ];
    }

    public function authorize()
    {
        return true;
    }
}
