<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

class ReceivedPaymentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date_format' => 'La fecha no es válida.',
        ];
    }
}
