<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'current_password' => ['required', 'current_password:web'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Escribe tu nuevo correo.',
            'email.email' => 'Escribe un correo válido.',
            'email.max' => 'El correo no puede pasar de 255 caracteres.',
            'email.unique' => 'Este correo ya está registrado.',
            'current_password.required' => 'Escribe tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
        ];
    }
}
