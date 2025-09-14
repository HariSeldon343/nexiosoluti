<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * Determina se l'utente è autorizzato a fare questa richiesta
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('edit-companies');
    }

    /**
     * Regole di validazione
     */
    public function rules(): array
    {
        $companyId = $this->route('id');

        return [
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('companies', 'code')->ignore($companyId)
            ],
            'vat_number' => 'nullable|string|max:50',
            'fiscal_code' => 'nullable|string|max:16',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:2',
            'postal_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:2',
            'parent_id' => [
                'nullable',
                'exists:companies,id',
                Rule::notIn([$companyId]) // Non può essere padre di se stessa
            ],
            'is_active' => 'boolean',
            'custom_fields' => 'nullable|array'
        ];
    }

    /**
     * Messaggi di errore personalizzati
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Il nome dell\'azienda è obbligatorio',
            'name.max' => 'Il nome non può superare i 255 caratteri',
            'code.unique' => 'Questo codice azienda è già in uso',
            'email.email' => 'Inserire un indirizzo email valido',
            'website.url' => 'Inserire un URL valido',
            'parent_id.exists' => 'L\'azienda padre selezionata non esiste',
            'parent_id.not_in' => 'Un\'azienda non può essere padre di se stessa'
        ];
    }
}