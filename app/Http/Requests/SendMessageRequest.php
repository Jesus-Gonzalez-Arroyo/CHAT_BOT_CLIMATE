<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conversation_id' => ['required', 'integer', 'exists:conversations,id'],
            'content' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'conversation_id.required' => 'El ID de la conversación es requerido',
            'conversation_id.exists' => 'La conversación no existe',
            'content.required' => 'El contenido del mensaje es requerido',
            'content.min' => 'El mensaje debe tener al menos 1 carácter',
            'content.max' => 'El mensaje no puede exceder los 2000 caracteres',
        ];
    }
}
