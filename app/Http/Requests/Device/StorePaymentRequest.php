<?php

namespace App\Http\Requests\Device;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:500'],
            'text' => ['nullable', 'string', 'max:2000'],
            'big_text' => ['nullable', 'string', 'max:5000'],
            'posted_at_ms' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }
}
