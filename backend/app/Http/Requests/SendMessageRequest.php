<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    /**
     * Determina se l'utente è autorizzato a fare questa richiesta
     */
    public function authorize(): bool
    {
        return true; // Verifica fatta nel controller
    }

    /**
     * Regole di validazione
     */
    public function rules(): array
    {
        return [
            'room_id' => 'required|exists:chat_rooms,id',
            'message' => 'required_without:attachments|string|max:5000',
            'type' => 'in:text,image,file,audio,video,location,system',
            'reply_to' => 'nullable|exists:chat_messages,id',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => [
                'file',
                'max:20480', // Max 20MB per allegato
                function ($attribute, $value, $fail) {
                    // Validazione basata sul tipo di messaggio
                    $type = $this->input('type', 'text');

                    switch ($type) {
                        case 'image':
                            $allowedMimes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                            break;
                        case 'audio':
                            $allowedMimes = ['mp3', 'wav', 'ogg', 'm4a'];
                            break;
                        case 'video':
                            $allowedMimes = ['mp4', 'avi', 'mov', 'webm'];
                            break;
                        default:
                            return; // Accetta qualsiasi file per tipo 'file'
                    }

                    $extension = strtolower($value->getClientOriginalExtension());
                    if (!in_array($extension, $allowedMimes)) {
                        $fail("Il file {$value->getClientOriginalName()} non è del tipo corretto per un messaggio {$type}");
                    }
                }
            ],
            'metadata' => 'nullable|array',
            'metadata.location' => 'required_if:type,location|array',
            'metadata.location.lat' => 'required_with:metadata.location|numeric|between:-90,90',
            'metadata.location.lng' => 'required_with:metadata.location|numeric|between:-180,180',
            'metadata.duration' => 'required_if:type,audio,video|nullable|integer|min:1'
        ];
    }

    /**
     * Messaggi di errore personalizzati
     */
    public function messages(): array
    {
        return [
            'room_id.required' => 'Selezionare una chat',
            'room_id.exists' => 'La chat selezionata non esiste',
            'message.required_without' => 'Il messaggio è obbligatorio se non ci sono allegati',
            'message.max' => 'Il messaggio non può superare i 5000 caratteri',
            'reply_to.exists' => 'Il messaggio a cui si sta rispondendo non esiste',
            'attachments.max' => 'Non è possibile inviare più di 5 allegati alla volta',
            'attachments.*.max' => 'Ogni allegato non può superare i 20MB',
            'metadata.location.lat.required_with' => 'La latitudine è obbligatoria per i messaggi di posizione',
            'metadata.location.lat.between' => 'La latitudine deve essere tra -90 e 90',
            'metadata.location.lng.required_with' => 'La longitudine è obbligatoria per i messaggi di posizione',
            'metadata.location.lng.between' => 'La longitudine deve essere tra -180 e 180'
        ];
    }

    /**
     * Prepara i dati per la validazione
     */
    protected function prepareForValidation(): void
    {
        // Imposta tipo di default
        if (!$this->has('type')) {
            if ($this->hasFile('attachments')) {
                $firstFile = $this->file('attachments')[0];
                $mime = $firstFile->getMimeType();

                if (str_starts_with($mime, 'image/')) {
                    $this->merge(['type' => 'image']);
                } elseif (str_starts_with($mime, 'audio/')) {
                    $this->merge(['type' => 'audio']);
                } elseif (str_starts_with($mime, 'video/')) {
                    $this->merge(['type' => 'video']);
                } else {
                    $this->merge(['type' => 'file']);
                }
            } else {
                $this->merge(['type' => 'text']);
            }
        }

        // Pulisci messaggio
        if ($this->has('message')) {
            $this->merge([
                'message' => trim($this->message)
            ]);
        }
    }
}