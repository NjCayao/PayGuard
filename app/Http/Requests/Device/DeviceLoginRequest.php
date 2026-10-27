<?php

namespace App\Http\Requests\Device;

use Illuminate\Foundation\Http\FormRequest;

class DeviceLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'brand' => ['required', 'string', 'max:40'],
            'model' => ['required', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Escribe el correo.',
            'email.email' => 'Escribe un correo válido.',
            'password.required' => 'Escribe la contraseña.',
            'brand.required' => 'Falta la marca del celular.',
            'model.required' => 'Falta el modelo del celular.',
        ];
    }
}
