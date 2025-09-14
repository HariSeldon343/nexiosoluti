<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Restituisce una risposta di successo
     */
    protected function success($data = null, string $message = 'Operazione completata con successo', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Restituisce una risposta di errore
     */
    protected function error(string $message = 'Si è verificato un errore', int $code = 400, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    /**
     * Restituisce una risposta per risorsa non trovata
     */
    protected function notFound(string $message = 'Risorsa non trovata')
    {
        return $this->error($message, 404);
    }

    /**
     * Restituisce una risposta per accesso non autorizzato
     */
    protected function unauthorized(string $message = 'Non autorizzato')
    {
        return $this->error($message, 401);
    }

    /**
     * Restituisce una risposta per azione non permessa
     */
    protected function forbidden(string $message = 'Azione non permessa')
    {
        return $this->error($message, 403);
    }

    /**
     * Restituisce una risposta per validazione fallita
     */
    protected function validationError($errors, string $message = 'Errore di validazione')
    {
        return $this->error($message, 422, $errors);
    }
}