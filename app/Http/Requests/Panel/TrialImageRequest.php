<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

class TrialImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('payguard.demo.trial.max_image_mb') * 1024;

        return [
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', "max:{$maxKb}"],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Elige una imagen para probar.',
            'image.image' => 'El archivo debe ser una imagen.',
            'image.mimes' => 'La imagen debe ser JPG, PNG o WebP.',
            'image.max' => 'La imagen es demasiado grande.',
        ];
    }
}
