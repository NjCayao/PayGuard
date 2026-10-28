<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'yape_holder_name' => ['nullable', 'string', 'max:120'],
            'match_window_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'recheck_minutes' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre del negocio.',
            'name.max' => 'El nombre del negocio no puede pasar de 120 caracteres.',
            'yape_holder_name.max' => 'El titular no puede pasar de 120 caracteres.',
            'match_window_minutes.required' => 'Escribe la ventana de comparación.',
            'match_window_minutes.integer' => 'La ventana debe ser un número de minutos.',
            'match_window_minutes.min' => 'La ventana debe ser de al menos 1 minuto.',
            'match_window_minutes.max' => 'La ventana no puede pasar de 60 minutos.',
            'recheck_minutes.required' => 'Escribe el tiempo de revisión.',
            'recheck_minutes.integer' => 'El tiempo de revisión debe ser un número de minutos.',
            'recheck_minutes.min' => 'La revisión debe durar al menos 1 minuto.',
            'recheck_minutes.max' => 'La revisión no puede pasar de 10 minutos.',
        ];
    }
}
