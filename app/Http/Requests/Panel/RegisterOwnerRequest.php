<?php

namespace App\Http\Requests\Panel;

use App\Services\PhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;

class RegisterOwnerRequest extends FormRequest
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
            'business_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'owner_whatsapp' => ['required', 'regex:/^\d{7,15}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'business_name.required' => 'Escribe el nombre del negocio.',
            'name.required' => 'Escribe tu nombre.',
            'email.required' => 'Escribe tu correo.',
            'email.email' => 'Escribe un correo válido.',
            'email.unique' => 'Este correo ya está registrado.',
            'password.required' => 'Escribe una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'owner_whatsapp.required' => 'Escribe tu número de WhatsApp.',
            'owner_whatsapp.regex' => 'Escribe un número de WhatsApp válido.',
        ];
    }
}
