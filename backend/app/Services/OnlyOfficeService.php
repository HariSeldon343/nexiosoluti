<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Servizio per integrazione con OnlyOffice Document Server
 * Gestisce editing collaborativo, versioning e callback per salvataggio
 */
class OnlyOfficeService
{
    protected $documentServerUrl;
    protected $jwtSecret;
    protected $callbackUrl;
    protected $maxFileSize;

    public function __construct()
    {
        $this->documentServerUrl = config('services.onlyoffice.document_server_url', 'http://localhost:8080');
        $this->jwtSecret = config('services.onlyoffice.jwt_secret', env('ONLYOFFICE_JWT_SECRET'));
        $this->callbackUrl = config('services.onlyoffice.callback_url', env('APP_URL') . '/api/onlyoffice/callback');
        $this->maxFileSize = config('services.onlyoffice.max_file_size', 50 * 1024 * 1024); // 50MB default
    }

    /**
     * Genera configurazione per l'editor OnlyOffice
     */
    public function generateEditorConfig(Document $document, User $user, array $options = []): array
    {
        // Genera chiave univoca per il documento
        $documentKey = $this->generateDocumentKey($document);

        // URL per accedere al documento
        $documentUrl = $this->getDocumentUrl($document);

        // Configurazione base dell'editor
        $config = [
            'type' => $options['type'] ?? 'desktop', // desktop, mobile, embedded
            'documentType' => $this->getDocumentType($document->extension),
            'document' => [
                'title' => $document->name,
                'url' => $documentUrl,
                'fileType' => $document->extension,
                'key' => $documentKey,
                'info' => [
                    'owner' => $document->owner->name ?? 'Sistema',
                    'uploaded' => $document->created_at->format('Y-m-d H:i:s'),
                    'favorite' => $document->is_favorite ?? false,
                ],
                'permissions' => [
                    'comment' => $options['canComment'] ?? true,
                    'download' => $options['canDownload'] ?? true,
                    'edit' => $options['canEdit'] ?? $this->canEdit($document, $user),
                    'fillForms' => true,
                    'modifyFilter' => true,
                    'modifyContentControl' => true,
                    'review' => $options['canReview'] ?? true,
                    'copy' => true,
                    'print' => $options['canPrint'] ?? true,
                ],
            ],
            'editorConfig' => [
                'actionLink' => null,
                'mode' => $this->canEdit($document, $user) ? 'edit' : 'view',
                'lang' => $user->locale ?? 'it',
                'callbackUrl' => $this->callbackUrl . '/' . $document->id,
                'coEditing' => [
                    'mode' => 'fast', // fast o strict
                    'change' => true,
                ],
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'group' => $user->tenant->name ?? 'Default',
                ],
                'customization' => [
                    'about' => true,
                    'comments' => true,
                    'compactHeader' => false,
                    'compactToolbar' => false,
                    'compatibleFeatures' => false,
                    'help' => true,
                    'toolbarNoTabs' => false,
                    'hideRightMenu' => false,
                    'hideRulers' => false,
                    'submitForm' => true,
                    'autosave' => true,
                    'forcesave' => false,
                    'logo' => [
                        'image' => asset('images/logo.png'),
                        'url' => config('app.url'),
                    ],
                    'customer' => [
                        'address' => config('app.company.address', ''),
                        'info' => config('app.company.info', 'NexioSolution'),
                        'logo' => asset('images/company-logo.png'),
                        'mail' => config('app.company.email', ''),
                        'name' => config('app.company.name', 'NexioSolution'),
                        'www' => config('app.url'),
                    ],
                    'feedback' => [
                        'url' => config('app.url') . '/feedback',
                        'visible' => true,
                    ],
                    'goback' => [
                        'blank' => false,
                        'text' => 'Torna ai documenti',
                        'url' => config('app.url') . '/documents',
                    ],
                    'reviewDisplay' => 'markup', // markup, simple, final, original
                    'showReviewChanges' => false,
                    'trackChanges' => true,
                    'unit' => 'cm', // cm, pt, inch
                    'zoom' => 100,
                ],
                'embedded' => [
                    'embedUrl' => $documentUrl,
                    'fullscreenUrl' => $documentUrl,
                    'saveUrl' => $documentUrl,
                    'shareUrl' => $documentUrl,
                    'toolbarDocked' => 'top',
                ],
                'plugins' => [
                    'autostart' => [
                        'asc.{0616AE85-5DBE-4B6B-A0A9-455C4F1503AD}',
                        'asc.{FFE1F462-1EA2-4391-990D-4CC84940B754}',
                    ],
                    'pluginsData' => [],
                ],
                'recent' => $this->getRecentDocuments($user),
                'templates' => $this->getDocumentTemplates($document->type),
            ],
            'events' => [
                'onAppReady' => 'onAppReady',
                'onCollaborativeChanges' => 'onCollaborativeChanges',
                'onDocumentReady' => 'onDocumentReady',
                'onDocumentStateChange' => 'onDocumentStateChange',
                'onDownloadAs' => 'onDownloadAs',
                'onError' => 'onError',
                'onInfo' => 'onInfo',
                'onMetaChange' => 'onMetaChange',
                'onOutdatedVersion' => 'onOutdatedVersion',
                'onReady' => 'onReady',
                'onRequestClose' => 'onRequestClose',
                'onRequestCompareFile' => 'onRequestCompareFile',
                'onRequestCreateNew' => 'onRequestCreateNew',
                'onRequestEditRights' => 'onRequestEditRights',
                'onRequestHistory' => 'onRequestHistory',
                'onRequestHistoryClose' => 'onRequestHistoryClose',
                'onRequestHistoryData' => 'onRequestHistoryData',
                'onRequestInsertImage' => 'onRequestInsertImage',
                'onRequestMailMergeRecipients' => 'onRequestMailMergeRecipients',
                'onRequestReferenceData' => 'onRequestReferenceData',
                'onRequestReferenceSource' => 'onRequestReferenceSource',
                'onRequestRename' => 'onRequestRename',
                'onRequestRestore' => 'onRequestRestore',
                'onRequestSaveAs' => 'onRequestSaveAs',
                'onRequestSendNotify' => 'onRequestSendNotify',
                'onRequestSharingSettings' => 'onRequestSharingSettings',
                'onRequestUsers' => 'onRequestUsers',
                'onWarning' => 'onWarning',
            ],
        ];

        // Aggiungi informazioni sulla cronologia versioni se disponibili
        if ($document->versions()->count() > 0) {
            $config['document']['history'] = $this->getDocumentHistory($document);
        }

        // Firma la configurazione con JWT se abilitato
        if ($this->jwtSecret) {
            $config['token'] = $this->generateJWT($config);
        }

        return $config;
    }

    /**
     * Genera una chiave univoca per il documento
     * La chiave cambia quando il documento viene modificato
     */
    public function generateDocumentKey(Document $document): string
    {
        // Combina ID, timestamp di modifica e versione per garantire unicità
        $key = $document->id . '_' . $document->updated_at->timestamp;

        if ($document->current_version) {
            $key .= '_v' . $document->current_version;
        }

        // Genera hash per avere una chiave più corta e sicura
        return substr(md5($key), 0, 20);
    }

    /**
     * Gestisce il callback di OnlyOffice per salvare le modifiche
     */
    public function handleCallback(int $documentId, array $callbackData): array
    {
        Log::info('OnlyOffice callback received', [
            'document_id' => $documentId,
            'status' => $callbackData['status'] ?? null,
        ]);

        $document = Document::findOrFail($documentId);

        // Verifica JWT se abilitato
        if ($this->jwtSecret && isset($callbackData['token'])) {
            try {
                $decoded = JWT::decode($callbackData['token'], new Key($this->jwtSecret, 'HS256'));
                $callbackData = (array) $decoded;
            } catch (\Exception $e) {
                Log::error('JWT verification failed', ['error' => $e->getMessage()]);
                return ['error' => 1, 'message' => 'Invalid token'];
            }
        }

        $status = $callbackData['status'] ?? 0;

        /**
         * Stati del documento:
         * 0 - Nessun documento con la chiave identificata trovato
         * 1 - Documento in fase di editing
         * 2 - Documento pronto per il salvataggio
         * 3 - Errore di salvataggio del documento
         * 4 - Documento chiuso senza modifiche
         * 6 - Documento in fase di editing, ma la sessione corrente è disconnessa
         * 7 - Errore durante la conversione del documento
         */

        switch ($status) {
            case 0:
                // Documento non trovato
                Log::warning('Document not found in OnlyOffice', ['key' => $callbackData['key'] ?? null]);
                break;

            case 1:
                // Documento in editing - aggiorna gli utenti connessi
                if (isset($callbackData['users'])) {
                    $this->updateConnectedUsers($document, $callbackData['users']);
                }
                break;

            case 2:
            case 3:
                // Documento pronto per il salvataggio o errore di salvataggio forzato
                if (isset($callbackData['url'])) {
                    $saved = $this->saveDocumentFromUrl($document, $callbackData['url'], $callbackData);

                    if (!$saved && $status == 2) {
                        return ['error' => 1, 'message' => 'Failed to save document'];
                    }
                }
                break;

            case 4:
                // Documento chiuso senza modifiche
                Log::info('Document closed without changes', ['document_id' => $documentId]);
                $this->clearConnectedUsers($document);
                break;

            case 6:
                // Sessione disconnessa, ma documento ancora in editing
                if (isset($callbackData['userdata'])) {
                    $this->handleDisconnectedUser($document, $callbackData['userdata']);
                }
                break;

            case 7:
                // Errore di conversione
                Log::error('Document conversion error', [
                    'document_id' => $documentId,
                    'error' => $callbackData['error'] ?? 'Unknown error',
                ]);
                break;
        }

        // Rispondi sempre con error: 0 per confermare la ricezione
        return ['error' => 0];
    }

    /**
     * Salva il documento dall'URL fornito da OnlyOffice
     */
    protected function saveDocumentFromUrl(Document $document, string $url, array $metadata = []): bool
    {
        try {
            // Scarica il file modificato
            $context = stream_context_create([
                'http' => [
                    'timeout' => 60,
                    'ignore_errors' => true,
                ],
            ]);

            $content = file_get_contents($url, false, $context);

            if ($content === false) {
                throw new \Exception('Failed to download document from OnlyOffice');
            }

            // Crea una nuova versione
            $version = $this->createDocumentVersion($document, $metadata);

            // Salva il file
            $path = 'documents/' . $document->tenant_id . '/' . $document->id . '/v' . $version->version . '.' . $document->extension;
            Storage::disk('local')->put($path, $content);

            // Aggiorna il documento
            $document->file_path = $path;
            $document->file_size = strlen($content);
            $document->current_version = $version->version;
            $document->last_modified_by = $metadata['users'][0] ?? null;
            $document->save();

            // Notifica gli utenti del salvataggio
            $this->notifyDocumentSaved($document, $version);

            Log::info('Document saved successfully', [
                'document_id' => $document->id,
                'version' => $version->version,
                'size' => $document->file_size,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to save document', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Crea una nuova versione del documento
     */
    protected function createDocumentVersion(Document $document, array $metadata = []): DocumentVersion
    {
        $lastVersion = $document->versions()->orderBy('version', 'desc')->first();
        $newVersionNumber = $lastVersion ? $lastVersion->version + 1 : 1;

        $version = new DocumentVersion([
            'document_id' => $document->id,
            'version' => $newVersionNumber,
            'created_by' => $metadata['users'][0] ?? Auth::id(),
            'changes' => $metadata['changes'] ?? [],
            'changeshistory' => $metadata['changeshistory'] ?? [],
            'comment' => $metadata['comment'] ?? 'Autosave',
            'file_size' => $document->file_size,
        ]);

        $version->save();

        return $version;
    }

    /**
     * Ottiene l'URL per accedere al documento
     */
    protected function getDocumentUrl(Document $document): string
    {
        // Genera URL firmato per accesso sicuro al documento
        return URL::temporarySignedRoute(
            'documents.download',
            now()->addHours(24),
            ['document' => $document->id]
        );
    }

    /**
     * Determina il tipo di documento per OnlyOffice
     */
    protected function getDocumentType(string $extension): string
    {
        $types = [
            // Documenti di testo
            'doc' => 'word',
            'docx' => 'word',
            'docm' => 'word',
            'dot' => 'word',
            'dotx' => 'word',
            'dotm' => 'word',
            'odt' => 'word',
            'rtf' => 'word',
            'txt' => 'word',
            'html' => 'word',
            'htm' => 'word',
            'mht' => 'word',
            'pdf' => 'word',
            'djvu' => 'word',
            'fb2' => 'word',
            'epub' => 'word',
            'xps' => 'word',

            // Fogli di calcolo
            'xls' => 'cell',
            'xlsx' => 'cell',
            'xlsm' => 'cell',
            'xlt' => 'cell',
            'xltx' => 'cell',
            'xltm' => 'cell',
            'ods' => 'cell',
            'csv' => 'cell',

            // Presentazioni
            'pps' => 'slide',
            'ppsx' => 'slide',
            'ppsm' => 'slide',
            'ppt' => 'slide',
            'pptx' => 'slide',
            'pptm' => 'slide',
            'pot' => 'slide',
            'potx' => 'slide',
            'potm' => 'slide',
            'odp' => 'slide',
        ];

        return $types[strtolower($extension)] ?? 'word';
    }

    /**
     * Verifica se l'utente può modificare il documento
     */
    protected function canEdit(Document $document, User $user): bool
    {
        // Logica di autorizzazione personalizzata
        if ($document->owner_id === $user->id) {
            return true;
        }

        if ($document->isSharedWith($user)) {
            return $document->getSharePermission($user) === 'edit';
        }

        return $user->hasPermission('documents.edit.all');
    }

    /**
     * Genera un JWT per firmare le richieste
     */
    protected function generateJWT(array $payload): string
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + 86400; // Valido per 24 ore

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    /**
     * Ottiene i documenti recenti dell'utente
     */
    protected function getRecentDocuments(User $user): array
    {
        $recent = Document::where('last_modified_by', $user->id)
            ->orWhere('owner_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        return $recent->map(function ($doc) {
            return [
                'title' => $doc->name,
                'url' => $this->getDocumentUrl($doc),
                'folder' => $doc->folder->name ?? 'Root',
            ];
        })->toArray();
    }

    /**
     * Ottiene i template disponibili per il tipo di documento
     */
    protected function getDocumentTemplates(string $type): array
    {
        $templates = [];

        // Carica template dal database o dal filesystem
        $templateModels = DocumentTemplate::where('type', $type)
            ->where('is_active', true)
            ->get();

        foreach ($templateModels as $template) {
            $templates[] = [
                'image' => $template->thumbnail_url,
                'title' => $template->name,
                'url' => $template->file_url,
            ];
        }

        return $templates;
    }

    /**
     * Ottiene la cronologia delle versioni del documento
     */
    protected function getDocumentHistory(Document $document): array
    {
        $history = [];
        $versions = $document->versions()->orderBy('version', 'desc')->get();

        foreach ($versions as $version) {
            $history[] = [
                'created' => $version->created_at->format('Y-m-d H:i:s'),
                'key' => $this->generateDocumentKey($document) . '_v' . $version->version,
                'version' => $version->version,
                'changes' => $version->changes,
                'serverVersion' => $this->getServerVersion(),
                'user' => [
                    'id' => (string) $version->created_by,
                    'name' => $version->creator->name ?? 'Unknown',
                ],
            ];
        }

        return [
            'currentVersion' => $document->current_version,
            'history' => $history,
        ];
    }

    /**
     * Aggiorna gli utenti connessi al documento
     */
    protected function updateConnectedUsers(Document $document, array $users): void
    {
        $connectedUsers = [];

        foreach ($users as $user) {
            $connectedUsers[] = [
                'id' => $user,
                'connected_at' => now(),
            ];
        }

        // Salva in cache o database
        cache()->put("document_{$document->id}_users", $connectedUsers, 300);

        // Broadcast evento per aggiornare l'UI
        broadcast(new DocumentUsersUpdated($document, $connectedUsers));
    }

    /**
     * Rimuove tutti gli utenti connessi al documento
     */
    protected function clearConnectedUsers(Document $document): void
    {
        cache()->forget("document_{$document->id}_users");
        broadcast(new DocumentUsersUpdated($document, []));
    }

    /**
     * Gestisce un utente disconnesso
     */
    protected function handleDisconnectedUser(Document $document, string $userId): void
    {
        $users = cache()->get("document_{$document->id}_users", []);
        $users = array_filter($users, fn($u) => $u['id'] !== $userId);
        cache()->put("document_{$document->id}_users", $users, 300);
        broadcast(new DocumentUsersUpdated($document, $users));
    }

    /**
     * Notifica il salvataggio del documento
     */
    protected function notifyDocumentSaved(Document $document, DocumentVersion $version): void
    {
        // Notifica via websocket
        broadcast(new DocumentSaved($document, $version));

        // Notifica via email se configurato
        if ($document->notify_on_save) {
            $document->notifyWatchers('document.saved', [
                'document' => $document,
                'version' => $version,
            ]);
        }
    }

    /**
     * Ottiene la versione del server OnlyOffice
     */
    protected function getServerVersion(): string
    {
        try {
            $response = Http::get($this->documentServerUrl . '/web-apps/apps/api/documents/api.js');

            if ($response->successful()) {
                // Estrai versione dal JavaScript
                if (preg_match('/DocsAPI\.DocEditor\.version\s*=\s*"([^"]+)"/', $response->body(), $matches)) {
                    return $matches[1];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Could not fetch OnlyOffice server version', ['error' => $e->getMessage()]);
        }

        return '7.0.0'; // Versione default
    }

    /**
     * Converte un documento in un altro formato
     */
    public function convertDocument(Document $document, string $toFormat): ?string
    {
        try {
            $config = [
                'async' => false,
                'filetype' => $document->extension,
                'outputtype' => $toFormat,
                'title' => $document->name . '.' . $toFormat,
                'url' => $this->getDocumentUrl($document),
            ];

            if ($this->jwtSecret) {
                $config['token'] = $this->generateJWT($config);
            }

            $response = Http::post($this->documentServerUrl . '/ConvertService.ashx', $config);

            if ($response->successful()) {
                $result = $response->json();

                if (isset($result['fileUrl'])) {
                    // Scarica il file convertito
                    $convertedContent = file_get_contents($result['fileUrl']);

                    // Salva il file convertito
                    $convertedPath = 'documents/converted/' . $document->id . '.' . $toFormat;
                    Storage::disk('local')->put($convertedPath, $convertedContent);

                    return $convertedPath;
                }
            }
        } catch (\Exception $e) {
            Log::error('Document conversion failed', [
                'document_id' => $document->id,
                'to_format' => $toFormat,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Verifica lo stato del server OnlyOffice
     */
    public function checkServerStatus(): array
    {
        try {
            $response = Http::timeout(5)->get($this->documentServerUrl . '/healthcheck');

            return [
                'status' => $response->successful() ? 'online' : 'offline',
                'version' => $this->getServerVersion(),
                'url' => $this->documentServerUrl,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'offline',
                'error' => $e->getMessage(),
                'url' => $this->documentServerUrl,
            ];
        }
    }
}