<?php

namespace App\Http\Requests\Panel;

use App\Services\PhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;

class StoreCashierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('whatsapp')) {
            $this->merge([
                'whatsapp' => PhoneNumbers::normalize($this->input('whatsapp')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'whatsapp' => ['required', 'regex:/^\d{7,15}$/', 'unique:cashiers,whatsapp'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre del cajero.',
            'name.max' => 'El nombre no puede pasar de 80 caracteres.',
            'whatsapp.required' => 'Escribe el número de WhatsApp.',
            'whatsapp.regex' => 'Escribe un número de WhatsApp válido.',
            'whatsapp.unique' => 'Este número ya está registrado.',
        ];
    }
}
