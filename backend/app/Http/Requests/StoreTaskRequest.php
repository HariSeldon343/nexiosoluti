<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determina se l'utente è autorizzato a fare questa richiesta
     */
    public function authorize(): bool
    {
        return true; // Tutti gli utenti autenticati possono creare task
    }

    /**
     * Regole di validazione
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'code' => 'nullable|string|max:50|unique:tasks,code',
            'status' => 'in:pending,in_progress,completed,cancelled,on_hold',
            'priority' => 'in:low,medium,high,urgent',
            'due_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0|max:9999',
            'progress' => 'integer|min:0|max:100',
            'company_id' => 'nullable|exists:companies,id',
            'project_id' => 'nullable|exists:projects,id',
            'parent_id' => 'nullable|exists:tasks,id',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'occurrences' => 'nullable|array',
            'occurrences.*.date' => 'required|date',
            'occurrences.*.notes' => 'nullable|string|max:500',
            'subtasks' => 'nullable|array',
            'subtasks.*.title' => 'required|string|max:255',
            'subtasks.*.description' => 'nullable|string|max:1000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240', // Max 10MB per file
            'custom_fields' => 'nullable|array'
        ];
    }

    /**
     * Messaggi di errore personalizzati
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Il titolo del task è obbligatorio',
            'title.max' => 'Il titolo non può superare i 255 caratteri',
            'code.unique' => 'Questo codice task è già in uso',
            'due_date.date' => 'La data di scadenza non è valida',
            'estimated_hours.numeric' => 'Le ore stimate devono essere un numero',
            'estimated_hours.max' => 'Le ore stimate non possono superare 9999',
            'progress.integer' => 'Il progresso deve essere un numero intero',
            'progress.min' => 'Il progresso non può essere negativo',
            'progress.max' => 'Il progresso non può superare il 100%',
            'company_id.exists' => 'L\'azienda selezionata non esiste',
            'project_id.exists' => 'Il progetto selezionato non esiste',
            'parent_id.exists' => 'Il task padre selezionato non esiste',
            'assignees.*.exists' => 'Uno o più utenti assegnati non esistono',
            'occurrences.*.date.required' => 'La data dell\'occorrenza è obbligatoria',
            'subtasks.*.title.required' => 'Il titolo del sotto-task è obbligatorio',
            'attachments.*.max' => 'Ogni allegato non può superare i 10MB'
        ];
    }

    /**
     * Prepara i dati per la validazione
     */
    protected function prepareForValidation(): void
    {
        // Imposta valori di default
        if (!$this->has('status')) {
            $this->merge(['status' => 'pending']);
        }

        if (!$this->has('priority')) {
            $this->merge(['priority' => 'medium']);
        }

        if (!$this->has('progress')) {
            $this->merge(['progress' => 0]);
        }

        // Valida che la data di inizio sia prima della data di scadenza
        if ($this->has('start_date') && $this->has('due_date')) {
            $startDate = strtotime($this->start_date);
            $dueDate = strtotime($this->due_date);

            if ($startDate > $dueDate) {
                $this->merge([
                    'start_date' => $this->due_date,
                    'due_date' => $this->start_date
                ]);
            }
        }
    }
}