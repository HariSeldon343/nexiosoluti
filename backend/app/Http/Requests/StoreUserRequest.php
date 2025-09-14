<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determina se l'utente è autorizzato a fare questa richiesta
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('create-users');
    }

    /**
     * Regole di validazione
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'username' => 'nullable|string|max:50|unique:users,username',
            'password' => [
                'required',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
            ],
            'phone' => 'nullable|string|max:50',
            'role' => 'nullable|string|exists:roles,name',
            'company_ids' => 'nullable|array',
            'company_ids.*' => 'exists:companies,id',
            'is_active' => 'boolean',
            'is_multi_tenant' => 'boolean',
            'language' => 'nullable|string|in:it,en,fr,de,es',
            'timezone' => 'nullable|timezone',
            'send_welcome_email' => 'boolean'
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
            'password.required' => 'La password è obbligatoria',
            'password.min' => 'La password deve contenere almeno 8 caratteri',
            'password.mixed' => 'La password deve contenere lettere maiuscole e minuscole',
            'password.numbers' => 'La password deve contenere almeno un numero',
            'password.symbols' => 'La password deve contenere almeno un simbolo',
            'password.uncompromised' => 'Questa password è stata compromessa in un data breach',
            'role.exists' => 'Il ruolo selezionato non esiste',
            'company_ids.*.exists' => 'Una o più aziende selezionate non esistono'
        ];
    }
}