// =============================================
// NEXIO COLLABORA - Centralized Error Handler
// =============================================

/**
 * ErrorHandler - Centralized error handling for all API calls
 * Maps HTTP status codes to user-friendly messages
 * Extracts and displays server error details when available
 * @version 1.0.0
 */

class ErrorHandler {
    constructor() {
        // HTTP Status code mappings
        this.statusMessages = {
            // Client errors (4xx)
            400: {
                title: 'Richiesta non valida',
                message: 'I dati inviati non sono validi. Verifica i campi e riprova.',
                type: 'error'
            },
            401: {
                title: 'Non autorizzato',
                message: 'Devi effettuare l\'accesso per continuare.',
                type: 'error'
            },
            403: {
                title: 'Accesso negato',
                message: 'Non hai i permessi necessari per questa operazione.',
                type: 'error'
            },
            404: {
                title: 'Risorsa non trovata',
                message: 'L\'endpoint richiesto non esiste. Verifica la configurazione.',
                type: 'error'
            },
            405: {
                title: 'Metodo non consentito',
                message: 'Il metodo HTTP utilizzato non è supportato.',
                type: 'error'
            },
            408: {
                title: 'Timeout richiesta',
                message: 'La richiesta ha impiegato troppo tempo. Riprova.',
                type: 'warning'
            },
            409: {
                title: 'Conflitto',
                message: 'La richiesta è in conflitto con lo stato corrente.',
                type: 'error'
            },
            422: {
                title: 'Dati non processabili',
                message: 'I dati forniti non possono essere elaborati.',
                type: 'error'
            },
            429: {
                title: 'Troppe richieste',
                message: 'Hai effettuato troppe richieste. Attendi qualche secondo.',
                type: 'warning'
            },

            // Server errors (5xx)
            500: {
                title: 'Errore del server',
                message: 'Si è verificato un errore interno. Il team tecnico è stato notificato.',
                type: 'error'
            },
            502: {
                title: 'Gateway non valido',
                message: 'Il server ha ricevuto una risposta non valida.',
                type: 'error'
            },
            503: {
                title: 'Servizio non disponibile',
                message: 'Il servizio è temporaneamente non disponibile. Riprova tra qualche minuto.',
                type: 'warning'
            },
            504: {
                title: 'Gateway timeout',
                message: 'Il server non ha risposto in tempo.',
                type: 'error'
            }
        };

        // Error code mappings for application-specific errors
        this.errorCodes = {
            'INVALID_CREDENTIALS': 'Email o password non corretti.',
            'USER_NOT_FOUND': 'Utente non trovato nel sistema.',
            'ACCOUNT_DISABLED': 'L\'account è stato disabilitato. Contatta l\'amministratore.',
            'ACCOUNT_LOCKED': 'Account bloccato per troppi tentativi. Riprova tra 15 minuti.',
            'SESSION_EXPIRED': 'La sessione è scaduta. Effettua nuovamente l\'accesso.',
            'INVALID_TOKEN': 'Token di sicurezza non valido.',
            'DATABASE_ERROR': 'Errore di connessione al database.',
            'VALIDATION_ERROR': 'I dati inseriti non sono validi.',
            'PERMISSION_DENIED': 'Non hai i permessi per questa operazione.',
            'TENANT_NOT_FOUND': 'Tenant non trovato.',
            'FILE_NOT_FOUND': 'File non trovato.',
            'UPLOAD_ERROR': 'Errore durante il caricamento del file.',
            'NETWORK_ERROR': 'Errore di rete. Verifica la connessione.',
            'UNKNOWN_ERROR': 'Si è verificato un errore sconosciuto.'
        };

        // Debug mode - can be toggled from console
        this.debugMode = localStorage.getItem('debugMode') === 'true';
    }

    /**
     * Enable or disable debug mode
     */
    setDebugMode(enabled) {
        this.debugMode = enabled;
        localStorage.setItem('debugMode', enabled ? 'true' : 'false');
        console.log(`Debug mode ${enabled ? 'enabled' : 'disabled'}`);
    }

    /**
     * Handle fetch response and extract error details
     */
    async handleResponse(response) {
        // Log response details in debug mode
        if (this.debugMode) {
            console.group('API Response');
            console.log('Status:', response.status);
            console.log('Status Text:', response.statusText);
            console.log('Headers:', Object.fromEntries(response.headers.entries()));
            console.groupEnd();
        }

        // If response is ok, return it
        if (response.ok) {
            return response;
        }

        // Extract error details
        let errorData = null;
        let errorMessage = '';
        let errorCode = null;

        // Try to parse JSON error response
        try {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                errorData = await response.json();
                errorMessage = errorData.message || errorData.error || '';
                errorCode = errorData.code || errorData.error_code || null;
            } else {
                // Try to get text response
                const text = await response.text();
                if (text) {
                    // Check if it's actually JSON
                    try {
                        errorData = JSON.parse(text);
                        errorMessage = errorData.message || errorData.error || text;
                        errorCode = errorData.code || null;
                    } catch {
                        errorMessage = text.substring(0, 200); // Limit text length
                    }
                }
            }
        } catch (e) {
            if (this.debugMode) {
                console.error('Error parsing response:', e);
            }
        }

        // Create detailed error object
        const error = {
            status: response.status,
            statusText: response.statusText,
            message: errorMessage,
            code: errorCode,
            data: errorData,
            url: response.url
        };

        // Log detailed error in debug mode
        if (this.debugMode) {
            console.error('API Error Details:', error);
        }

        throw error;
    }

    /**
     * Format error for display
     */
    formatError(error) {
        let title = 'Errore';
        let message = 'Si è verificato un errore imprevisto.';
        let type = 'error';
        let details = null;
        let fields = [];

        // Handle different error types
        if (error.status) {
            // HTTP error with status code

            // Special handling for 400 and 401 with structured errors
            if (error.status === 400 && error.data?.error) {
                title = 'Richiesta non valida';
                type = 'error';

                // Extract fields and message from structured error
                const errorObj = error.data.error;
                if (errorObj.fields && errorObj.fields.length > 0) {
                    fields = errorObj.fields;
                    const fieldNames = fields.map(f => {
                        if (f === 'email') return 'Email';
                        if (f === 'password') return 'Password';
                        if (f === 'action') return 'Action';
                        return f;
                    }).join(', ');
                    message = `Campo mancante: ${fieldNames}`;

                    // Override title based on error code
                    if (errorObj.code === 'missing_fields') {
                        title = 'Campi obbligatori';
                    } else if (errorObj.code === 'invalid_json') {
                        title = 'Formato non valido';
                        message = errorObj.message || 'Il formato dei dati non è valido';
                    } else if (errorObj.code === 'empty_body') {
                        title = 'Richiesta vuota';
                        message = errorObj.message || 'La richiesta è vuota';
                    }
                } else if (errorObj.message) {
                    message = errorObj.message;
                }

                // Use error code if available
                if (errorObj.code && this.errorCodes[errorObj.code]) {
                    message = this.errorCodes[errorObj.code];
                }
            } else if (error.status === 401) {
                // Always show "Credenziali non valide" for 401
                title = 'Accesso negato';
                message = 'Credenziali non valide';
                type = 'error';

                // But use server message if provided
                if (error.data?.error?.message) {
                    message = error.data.error.message;
                }
            } else {
                // Use standard status message
                const statusInfo = this.statusMessages[error.status];
                if (statusInfo) {
                    title = statusInfo.title;
                    message = statusInfo.message;
                    type = statusInfo.type;
                } else {
                    title = `Errore HTTP ${error.status}`;
                    message = error.statusText || 'Errore sconosciuto';
                }

                // Override with server-provided message if available
                if (error.data?.error?.message) {
                    message = error.data.error.message;
                } else if (error.message) {
                    message = error.message;
                }
            }

            // Check for specific error codes (if not already handled)
            if (error.code && this.errorCodes[error.code] && error.status !== 400 && error.status !== 401) {
                message = this.errorCodes[error.code];
            }

            // Add details in debug mode
            if (this.debugMode) {
                details = {
                    status: error.status,
                    code: error.code || error.data?.error?.code,
                    fields: fields,
                    url: error.url,
                    raw: error.data
                };
            }
        } else if (error.message) {
            // JavaScript error or custom error
            // NEVER show "Errore di connessione" for 400/401 - those are application errors, not network errors
            if (error.message.includes('fetch') && !error.status) {
                title = 'Errore di connessione';
                message = 'Impossibile connettersi al server. Verifica la connessione.';
                type = 'error';
            } else if (error.message.includes('JSON') && !error.status) {
                title = 'Errore di formato';
                message = 'Risposta del server non valida.';
                type = 'error';
            } else {
                message = error.message;
            }

            if (this.debugMode) {
                details = {
                    error: error.toString(),
                    stack: error.stack
                };
            }
        } else if (typeof error === 'string') {
            message = error;
        }

        return {
            title,
            message,
            type,
            details,
            fields
        };
    }

    /**
     * Display error using toast notification
     */
    showError(error, customOptions = {}) {
        const formatted = this.formatError(error);

        // Merge with custom options
        const options = {
            ...formatted,
            ...customOptions
        };

        // Build message with details if in debug mode
        let fullMessage = options.message;
        if (options.details) {
            fullMessage += '\n\n[Debug Info]\n';
            fullMessage += JSON.stringify(options.details, null, 2);
        }

        // Use existing toast function if available
        if (typeof window.showToast === 'function') {
            window.showToast(options.type, options.title, fullMessage);
        } else if (typeof window.authV2?.showToast === 'function') {
            window.authV2.showToast(options.type, options.title, fullMessage);
        } else {
            // Fallback to console and alert
            console.error(`${options.title}: ${fullMessage}`);
            if (!this.debugMode) {
                alert(`${options.title}\n\n${options.message}`);
            }
        }

        return formatted;
    }

    /**
     * Create a fetch wrapper with automatic error handling
     */
    async fetch(url, options = {}) {
        try {
            // Add default headers
            const headers = {
                'Accept': 'application/json',
                ...options.headers
            };

            // Add JSON content type for POST/PUT/PATCH with body
            if (options.body && ['POST', 'PUT', 'PATCH'].includes(options.method?.toUpperCase())) {
                if (typeof options.body === 'object' && !(options.body instanceof FormData)) {
                    headers['Content-Type'] = 'application/json';
                    options.body = JSON.stringify(options.body);
                }
            }

            options.headers = headers;

            // Log request in debug mode
            if (this.debugMode) {
                console.group('API Request');
                console.log('URL:', url);
                console.log('Options:', options);
                console.groupEnd();
            }

            const response = await fetch(url, options);
            return await this.handleResponse(response);

        } catch (error) {
            // Log and re-throw
            if (this.debugMode) {
                console.error('Fetch error:', error);
            }
            throw error;
        }
    }

    /**
     * Convenience methods for common HTTP methods
     */
    async get(url, options = {}) {
        return this.fetch(url, { ...options, method: 'GET' });
    }

    async post(url, body, options = {}) {
        return this.fetch(url, { ...options, method: 'POST', body });
    }

    async put(url, body, options = {}) {
        return this.fetch(url, { ...options, method: 'PUT', body });
    }

    async delete(url, options = {}) {
        return this.fetch(url, { ...options, method: 'DELETE' });
    }

    /**
     * Handle form submission with error handling
     */
    async handleForm(form, url, successCallback) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn?.innerHTML;

        try {
            // Disable submit button
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Elaborazione...</span>';
            }

            // Get form data
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            // Make request
            const response = await this.post(url, data);
            const result = await response.json();

            // Call success callback
            if (successCallback) {
                await successCallback(result);
            }

            return result;

        } catch (error) {
            // Show error
            this.showError(error);
            throw error;

        } finally {
            // Re-enable submit button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
    }

    /**
     * Retry failed request with exponential backoff
     */
    async retry(fn, maxRetries = 3, delay = 1000) {
        let lastError;

        for (let i = 0; i < maxRetries; i++) {
            try {
                return await fn();
            } catch (error) {
                lastError = error;

                // Don't retry on client errors (4xx)
                if (error.status && error.status >= 400 && error.status < 500) {
                    throw error;
                }

                // Wait before retry with exponential backoff
                if (i < maxRetries - 1) {
                    const waitTime = delay * Math.pow(2, i);
                    if (this.debugMode) {
                        console.log(`Retry ${i + 1}/${maxRetries} after ${waitTime}ms`);
                    }
                    await new Promise(resolve => setTimeout(resolve, waitTime));
                }
            }
        }

        throw lastError;
    }
}

// Create global instance
window.ErrorHandler = new ErrorHandler();

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ErrorHandler;
}