<?php

namespace App\Http\Requests\Panel;

use App\Services\PhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnerWhatsappRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('owner_whatsapp')) {
            $this->merge([
                'owner_whatsapp' => PhoneNumbers::normalize($this->input('owner_whatsapp')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'owner_whatsapp' => ['required', 'regex:/^\d{7,15}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'owner_whatsapp.required' => 'Escribe tu número de WhatsApp.',
            'owner_whatsapp.regex' => 'Escribe un número de WhatsApp válido.',
        ];
    }
}
