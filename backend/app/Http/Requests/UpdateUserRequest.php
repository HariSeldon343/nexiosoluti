<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determina se l'utente è autorizzato a fare questa richiesta
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $targetUserId = $this->route('id');

        // L'utente può modificare se stesso o deve avere il permesso
        return $user->id == $targetUserId || $user->hasPermissionTo('edit-users');
    }

    /**
     * Regole di validazione
     */
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'username' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'username')->ignore($userId)
            ],
            'password' => [
                'nullable',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
            ],
            'phone' => 'nullable|string|max:50',
            'avatar' => 'nullable|image|max:2048|mimes:jpg,jpeg,png,webp',
            'is_active' => 'boolean',
            'language' => 'nullable|string|in:it,en,fr,de,es',
            'timezone' => 'nullable|timezone',
            'notification_settings' => 'nullable|array'
        ];
    }

    /**
     * Messaggi di errore personalizzati
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Il nome è obbligatorio',
            'email.required' => 'L\'email è obbligatoria',
            'email.email' => 'Inserire un indirizzo email valido',
            'email.unique' => 'Questo indirizzo email è già registrato',
            'username.unique' => 'Questo username è già in uso',
            'password.min' => 'La password deve contenere almeno 8 caratteri',
            'password.mixed' => 'La password deve contenere lettere maiuscole e minuscole',
            'password.numbers' => 'La password deve contenere almeno un numero',
            'password.symbols' => 'La password deve contenere almeno un simbolo',
            'password.uncompromised' => 'Questa password è stata compromessa in un data breach',
            'avatar.image' => 'Il file deve essere un\'immagine',
            'avatar.max' => 'L\'immagine non può superare i 2MB',
            'avatar.mimes' => 'L\'immagine deve essere in formato JPG, PNG o WebP'
        ];
    }
}