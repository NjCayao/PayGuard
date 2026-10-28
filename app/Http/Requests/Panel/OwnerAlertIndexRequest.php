<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OwnerAlertIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in(['queued', 'sent', 'delivered', 'read', 'failed', 'simulated'])],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.date_format' => 'La fecha inicial no es válida.',
            'date_to.date_format' => 'La fecha final no es válida.',
            'date_to.after_or_equal' => 'La fecha final debe ser posterior a la inicial.',
            'status.in' => 'El estado no es válido.',
        ];
    }
}
