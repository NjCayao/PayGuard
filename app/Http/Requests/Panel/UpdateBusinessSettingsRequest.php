<?php

namespace App\Http\Requests\Panel;

use App\Services\PhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessSettingsRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'yape_holder_name' => ['nullable', 'string', 'max:120'],
            'owner_whatsapp' => ['required', 'regex:/^\d{7,15}$/'],
            'match_window_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'recheck_minutes' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre del negocio.',
            'owner_whatsapp.required' => 'Escribe tu número de WhatsApp.',
            'owner_whatsapp.regex' => 'Escribe un número de WhatsApp válido.',
            'match_window_minutes.min' => 'La ventana debe ser de al menos 1 minuto.',
            'match_window_minutes.max' => 'La ventana no puede pasar de 60 minutos.',
            'recheck_minutes.min' => 'La revisión debe durar al menos 1 minuto.',
            'recheck_minutes.max' => 'La revisión no puede pasar de 10 minutos.',
        ];
    }
}
