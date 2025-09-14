<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    /**
     * Determina se l'utente è autorizzato a fare questa richiesta
     */
    public function authorize(): bool
    {
        return true; // Tutti gli utenti autenticati possono creare eventi
    }

    /**
     * Regole di validazione
     */
    public function rules(): array
    {
        return [
            'calendar_id' => 'required|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'location' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'all_day' => 'boolean',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'recurrence_rule' => 'nullable|string|max:500',
            'recurrence_end' => 'nullable|date|after:start_date',
            'attendees' => 'nullable|array',
            'attendees.*' => 'exists:users,id',
            'reminders' => 'nullable|array',
            'reminders.*.type' => 'required|in:email,push,sms',
            'reminders.*.minutes_before' => 'required|integer|min:0|max:10080', // Max 1 settimana
            'status' => 'in:confirmed,tentative,cancelled',
            'visibility' => 'in:public,private,confidential',
            'meeting_url' => 'nullable|url|max:500',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240' // Max 10MB per file
        ];
    }

    /**
     * Messaggi di errore personalizzati
     */
    public function messages(): array
    {
        return [
            'calendar_id.required' => 'Selezionare un calendario',
            'title.required' => 'Il titolo dell\'evento è obbligatorio',
            'title.max' => 'Il titolo non può superare i 255 caratteri',
            'start_date.required' => 'La data di inizio è obbligatoria',
            'end_date.required' => 'La data di fine è obbligatoria',
            'end_date.after_or_equal' => 'La data di fine deve essere successiva o uguale a quella di inizio',
            'color.regex' => 'Il colore deve essere in formato esadecimale (#RRGGBB)',
            'attendees.*.exists' => 'Uno o più partecipanti selezionati non esistono',
            'reminders.*.type.required' => 'Specificare il tipo di promemoria',
            'reminders.*.minutes_before.required' => 'Specificare quando inviare il promemoria',
            'reminders.*.minutes_before.max' => 'Il promemoria può essere impostato al massimo una settimana prima',
            'meeting_url.url' => 'L\'URL della riunione non è valido',
            'attachments.*.max' => 'Ogni allegato non può superare i 10MB'
        ];
    }

    /**
     * Prepara i dati per la validazione
     */
    protected function prepareForValidation(): void
    {
        // Converti all_day in boolean
        if ($this->has('all_day')) {
            $this->merge([
                'all_day' => filter_var($this->all_day, FILTER_VALIDATE_BOOLEAN)
            ]);
        }

        // Se è un evento tutto il giorno, imposta gli orari appropriati
        if ($this->boolean('all_day')) {
            $this->merge([
                'start_date' => date('Y-m-d 00:00:00', strtotime($this->start_date)),
                'end_date' => date('Y-m-d 23:59:59', strtotime($this->end_date))
            ]);
        }
    }
}