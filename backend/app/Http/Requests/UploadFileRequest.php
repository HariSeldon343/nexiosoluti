<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    /**
     * Determina se l'utente è autorizzato a fare questa richiesta
     */
    public function authorize(): bool
    {
        return true; // Tutti gli utenti autenticati possono caricare file
    }

    /**
     * Regole di validazione
     */
    public function rules(): array
    {
        return [
            'files' => 'required|array|min:1|max:10',
            'files.*' => [
                'required',
                'file',
                'max:51200', // Max 50MB per file
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,gif,webp,svg,mp4,avi,mov,mp3,wav,zip,rar,7z'
            ],
            'folder_id' => 'nullable|exists:files,id',
            'company_id' => 'nullable|exists:companies,id',
            'description' => 'nullable|string|max:1000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'requires_approval' => 'boolean',
            'share_with' => 'nullable|array',
            'share_with.*' => 'exists:users,id',
            'permissions' => 'in:view,download,edit',
            'expires_at' => 'nullable|date|after:now'
        ];
    }

    /**
     * Messaggi di errore personalizzati
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Selezionare almeno un file da caricare',
            'files.min' => 'Selezionare almeno un file',
            'files.max' => 'Non è possibile caricare più di 10 file alla volta',
            'files.*.required' => 'File non valido',
            'files.*.file' => 'Il file selezionato non è valido',
            'files.*.max' => 'Il file non può superare i 50MB',
            'files.*.mimes' => 'Tipo di file non supportato. Formati accettati: PDF, documenti Office, immagini, video, audio, archivi',
            'folder_id.exists' => 'La cartella selezionata non esiste',
            'company_id.exists' => 'L\'azienda selezionata non esiste',
            'share_with.*.exists' => 'Uno o più utenti selezionati non esistono',
            'expires_at.after' => 'La data di scadenza deve essere futura'
        ];
    }

    /**
     * Ottieni nomi personalizzati per gli attributi
     */
    public function attributes(): array
    {
        $attributes = [];

        if ($this->hasFile('files')) {
            foreach ($this->file('files') as $key => $file) {
                $attributes["files.{$key}"] = $file->getClientOriginalName();
            }
        }

        return $attributes;
    }
}