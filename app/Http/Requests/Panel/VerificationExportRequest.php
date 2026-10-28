<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerificationExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'cashier_id' => [
                'nullable',
                'integer',
                Rule::exists('cashiers', 'id')->where('business_id', $this->user()->business_id),
            ],
            'status' => ['nullable', Rule::in([
                'processing', 'pending', 'verified', 'not_found', 'mismatch', 'reused', 'error',
            ])],
        ];
    }

    public function messages(): array
    {
        return [
            'format.required' => 'Elige un formato.',
            'format.in' => 'El formato no es válido.',
            'date_from.date_format' => 'La fecha inicial no es válida.',
            'date_to.date_format' => 'La fecha final no es válida.',
            'date_to.after_or_equal' => 'La fecha final debe ser posterior a la inicial.',
            'cashier_id.exists' => 'El cajero no existe.',
            'status.in' => 'El estado no es válido.',
        ];
    }
}
