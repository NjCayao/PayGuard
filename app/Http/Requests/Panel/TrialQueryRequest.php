<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

class TrialQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'text.required' => 'Escribe una consulta para probar.',
            'text.max' => 'La consulta es demasiado larga.',
        ];
    }
}
