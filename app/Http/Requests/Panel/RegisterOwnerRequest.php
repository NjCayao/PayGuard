<?php

namespace App\Http\Requests\Panel;

use App\Services\PhoneNumbers;
use App\Services\Security\TurnstileVerifier;
use Closure;
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
        $honeypot = (string) config('payguard.registration.honeypot_field', 'website');

        return [
            'business_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'owner_whatsapp' => ['required', 'regex:/^\d{7,15}$/'],
            $honeypot => ['nullable', 'size:0'],
            'cf-turnstile-response' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                $verifier = app(TurnstileVerifier::class);

                if (! $verifier->passes(is_string($value) ? $value : null, $this->ip())) {
                    $fail('La verificación anti-robots falló. Inténtalo de nuevo.');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'business_name.required' => 'Escribe el nombre del negocio.',
            'business_name.max' => 'El nombre del negocio no puede pasar de 120 caracteres.',
            'name.required' => 'Escribe tu nombre.',
            'name.max' => 'Tu nombre no puede pasar de 255 caracteres.',
            'email.required' => 'Escribe tu correo.',
            'email.email' => 'Escribe un correo válido.',
            'email.unique' => 'Este correo ya está registrado.',
            'email.max' => 'El correo no puede pasar de 255 caracteres.',
            'password.required' => 'Escribe una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'owner_whatsapp.required' => 'Escribe tu número de WhatsApp.',
            'owner_whatsapp.regex' => 'Escribe un número de WhatsApp válido.',
            'website.size' => 'No se pudo crear la cuenta.',
            'cf-turnstile-response.required' => 'Completa la verificación anti-robots.',
        ];
    }
}
