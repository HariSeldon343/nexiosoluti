<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadFileRequest;
use App\Models\File;
use App\Models\FileVersion;
use App\Services\TenantService;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    protected TenantService $tenantService;
    protected FileService $fileService;

    public function __construct(
        TenantService $tenantService,
        FileService $fileService
    ) {
        $this->tenantService = $tenantService;
        $this->fileService = $fileService;
    }

    /**
     * Lista file e cartelle
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $query = File::where('tenant_id', $tenant->id)
                ->with(['uploadedBy', 'company', 'sharedWith']);

            // Filtra per cartella padre
            if ($request->has('folder_id')) {
                $query->where('folder_id', $request->input('folder_id'));
            } else {
                $query->whereNull('folder_id'); // Root level
            }

            // Filtra per azienda
            if ($request->has('company_id')) {
                $query->where('company_id', $request->input('company_id'));
            }

            // Filtra per tipo
            if ($request->has('type')) {
                if ($request->input('type') === 'folder') {
                    $query->where('is_folder', true);
                } elseif ($request->input('type') === 'file') {
                    $query->where('is_folder', false);
                }
            }

            // Filtra per estensione
            if ($request->has('extension')) {
                $query->where('extension', $request->input('extension'));
            }

            // Ricerca
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', "%{$search}%");
            }

            // Solo file condivisi con l'utente
            if ($request->boolean('shared_with_me')) {
                $query->whereHas('sharedWith', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }

            // Solo file in attesa di approvazione
            if ($request->boolean('pending_approval')) {
                $query->where('approval_status', 'pending');
            }

            // Ordinamento
            $sortBy = $request->input('sort_by', 'name');
            $sortDesc = $request->boolean('sort_desc', false);

            if ($sortBy === 'type') {
                $query->orderBy('is_folder', $sortDesc ? 'asc' : 'desc')
                      ->orderBy('name', $sortDesc ? 'desc' : 'asc');
            } else {
                $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');
            }

            // Paginazione
            $perPage = $request->input('per_page', 50);
            $files = $query->paginate($perPage);

            // Aggiungi informazioni extra
            $files->getCollection()->transform(function ($file) use ($user) {
                if (!$file->is_folder) {
                    $file->download_url = route('api.files.download', $file->id);
                    $file->preview_url = $this->fileService->canPreview($file->extension)
                        ? route('api.files.preview', $file->id)
                        : null;
                    $file->can_approve = $user->hasPermissionTo('approve-files') &&
                                        $file->approval_status === 'pending';
                }

                $file->can_edit = $file->uploaded_by === $user->id ||
                                  $user->hasPermissionTo('edit-all-files');
                $file->can_delete = $file->uploaded_by === $user->id ||
                                   $user->hasPermissionTo('delete-all-files');

                return $file;
            });

            // Breadcrumb per navigazione
            $breadcrumb = [];
            if ($request->has('folder_id')) {
                $breadcrumb = $this->getBreadcrumb($request->input('folder_id'));
            }

            Log::info('Files list retrieved', [
                'user_id' => $user->id,
                'folder_id' => $request->input('folder_id'),
                'count' => $files->total()
            ]);

            return response()->json([
                'success' => true,
                'data' => $files,
                'breadcrumb' => $breadcrumb
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching files', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero dei file'
            ], 500);
        }
    }

    /**
     * Upload file multipli
     */
    public function upload(UploadFileRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Verifica spazio disponibile
            if (!$this->fileService->hasStorageSpace($tenant, $request->file('files'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Spazio di archiviazione insufficiente'
                ], 422);
            }

            DB::beginTransaction();

            $uploadedFiles = [];

            foreach ($request->file('files') as $uploadedFile) {
                // Genera nome univoco
                $originalName = $uploadedFile->getClientOriginalName();
                $extension = $uploadedFile->getClientOriginalExtension();
                $fileName = Str::uuid() . '.' . $extension;

                // Determina percorso
                $path = "tenants/{$tenant->id}/files";
                if ($request->has('company_id')) {
                    $path .= "/company_{$request->input('company_id')}";
                }

                // Salva file
                $storedPath = $uploadedFile->storeAs($path, $fileName, 'private');

                // Crea record nel database
                $file = File::create([
                    'tenant_id' => $tenant->id,
                    'company_id' => $request->input('company_id'),
                    'folder_id' => $request->input('folder_id'),
                    'name' => $originalName,
                    'path' => $storedPath,
                    'size' => $uploadedFile->getSize(),
                    'mime_type' => $uploadedFile->getMimeType(),
                    'extension' => $extension,
                    'uploaded_by' => $user->id,
                    'is_folder' => false,
                    'approval_status' => $request->boolean('requires_approval') ? 'pending' : 'approved',
                    'metadata' => [
                        'original_name' => $originalName,
                        'upload_ip' => $request->ip(),
                        'user_agent' => $request->userAgent()
                    ]
                ]);

                // Crea prima versione
                FileVersion::create([
                    'file_id' => $file->id,
                    'version_number' => 1,
                    'path' => $storedPath,
                    'size' => $file->size,
                    'uploaded_by' => $user->id,
                    'comment' => 'Versione iniziale'
                ]);

                // Scansione antivirus se configurata
                if (config('app.antivirus_enabled')) {
                    $this->fileService->scanFile($file);
                }

                $uploadedFiles[] = $file;

                // Log upload
                Log::info('File uploaded', [
                    'file_id' => $file->id,
                    'name' => $originalName,
                    'size' => $file->size,
                    'uploaded_by' => $user->id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($uploadedFiles) . ' file caricati con successo',
                'data' => $uploadedFiles
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error uploading files', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel caricamento dei file'
            ], 500);
        }
    }

    /**
     * Download file
     */
    public function download($id)
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $file = File::where('tenant_id', $tenant->id)
                ->where('is_folder', false)
                ->findOrFail($id);

            // Verifica permessi
            if (!$this->canAccessFile($file, $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            // Log download
            DB::table('file_downloads')->insert([
                'file_id' => $file->id,
                'user_id' => $user->id,
                'downloaded_at' => now(),
                'ip_address' => request()->ip()
            ]);

            // Incrementa contatore download
            $file->increment('download_count');

            // Restituisci file
            if (Storage::disk('private')->exists($file->path)) {
                return Storage::disk('private')->download(
                    $file->path,
                    $file->name,
                    [
                        'Content-Type' => $file->mime_type,
                        'Cache-Control' => 'no-cache, no-store, must-revalidate'
                    ]
                );
            }

            return response()->json([
                'success' => false,
                'message' => 'File non trovato'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error downloading file', [
                'file_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel download del file'
            ], 500);
        }
    }

    /**
     * Anteprima file
     */
    public function preview($id)
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $file = File::where('tenant_id', $tenant->id)
                ->where('is_folder', false)
                ->findOrFail($id);

            // Verifica permessi
            if (!$this->canAccessFile($file, $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            // Verifica se il file può essere visualizzato in anteprima
            if (!$this->fileService->canPreview($file->extension)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anteprima non disponibile per questo tipo di file'
                ], 422);
            }

            // Genera anteprima
            $preview = $this->fileService->generatePreview($file);

            if ($preview) {
                return response($preview['content'], 200)
                    ->header('Content-Type', $preview['mime_type'])
                    ->header('Cache-Control', 'public, max-age=3600');
            }

            return response()->json([
                'success' => false,
                'message' => 'Impossibile generare anteprima'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error generating file preview', [
                'file_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella generazione dell\'anteprima'
            ], 500);
        }
    }

    /**
     * Crea cartella
     */
    public function createFolder(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'folder_id' => 'nullable|exists:files,id',
                'company_id' => 'nullable|exists:companies,id'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Verifica unicità nome nella stessa cartella
            $exists = File::where('tenant_id', $tenant->id)
                ->where('name', $request->input('name'))
                ->where('is_folder', true)
                ->where(function ($q) use ($request) {
                    if ($request->has('folder_id')) {
                        $q->where('folder_id', $request->input('folder_id'));
                    } else {
                        $q->whereNull('folder_id');
                    }
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Una cartella con questo nome esiste già'
                ], 422);
            }

            $folder = File::create([
                'tenant_id' => $tenant->id,
                'company_id' => $request->input('company_id'),
                'folder_id' => $request->input('folder_id'),
                'name' => $request->input('name'),
                'is_folder' => true,
                'uploaded_by' => $user->id
            ]);

            Log::info('Folder created', [
                'folder_id' => $folder->id,
                'name' => $folder->name,
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cartella creata con successo',
                'data' => $folder
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating folder', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella creazione della cartella'
            ], 500);
        }
    }

    /**
     * Sposta file/cartelle
     */
    public function move(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file_ids' => 'required|array',
                'file_ids.*' => 'exists:files,id',
                'target_folder_id' => 'nullable|exists:files,id'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            DB::beginTransaction();

            $movedCount = 0;
            foreach ($request->input('file_ids') as $fileId) {
                $file = File::where('tenant_id', $tenant->id)->find($fileId);

                if (!$file) continue;

                // Verifica permessi
                if (!$this->canEditFile($file, $user)) {
                    continue;
                }

                // Evita spostamento ricorsivo
                if ($file->is_folder && $request->input('target_folder_id')) {
                    if ($this->isChildFolder($file->id, $request->input('target_folder_id'))) {
                        continue;
                    }
                }

                $file->folder_id = $request->input('target_folder_id');
                $file->save();
                $movedCount++;
            }

            DB::commit();

            Log::info('Files moved', [
                'count' => $movedCount,
                'target_folder' => $request->input('target_folder_id'),
                'moved_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$movedCount} elementi spostati con successo"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error moving files', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nello spostamento dei file'
            ], 500);
        }
    }

    /**
     * Rinomina file/cartella
     */
    public function rename(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $file = File::where('tenant_id', $tenant->id)->findOrFail($id);

            // Verifica permessi
            if (!$this->canEditFile($file, $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            $oldName = $file->name;
            $file->name = $request->input('name');
            $file->save();

            Log::info('File renamed', [
                'file_id' => $id,
                'old_name' => $oldName,
                'new_name' => $file->name,
                'renamed_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rinominato con successo',
                'data' => $file
            ]);
        } catch (\Exception $e) {
            Log::error('Error renaming file', [
                'file_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella rinomina'
            ], 500);
        }
    }

    /**
     * Elimina file/cartella
     */
    public function delete(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file_ids' => 'required|array',
                'file_ids.*' => 'exists:files,id'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            DB::beginTransaction();

            $deletedCount = 0;
            foreach ($request->input('file_ids') as $fileId) {
                $file = File::where('tenant_id', $tenant->id)->find($fileId);

                if (!$file) continue;

                // Verifica permessi
                if (!$this->canDeleteFile($file, $user)) {
                    continue;
                }

                // Se è una cartella, elimina ricorsivamente
                if ($file->is_folder) {
                    $this->deleteFolder($file);
                } else {
                    // Elimina file fisico
                    Storage::disk('private')->delete($file->path);

                    // Elimina versioni
                    foreach ($file->versions as $version) {
                        Storage::disk('private')->delete($version->path);
                    }
                }

                $file->delete();
                $deletedCount++;
            }

            DB::commit();

            Log::warning('Files deleted', [
                'count' => $deletedCount,
                'deleted_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} elementi eliminati con successo"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error deleting files', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'eliminazione dei file'
            ], 500);
        }
    }

    /**
     * Condividi file
     */
    public function share(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id',
                'permissions' => 'required|in:view,download,edit',
                'expires_at' => 'nullable|date|after:now'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $file = File::where('tenant_id', $tenant->id)->findOrFail($id);

            // Verifica permessi
            if (!$this->canShareFile($file, $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a condividere questo file'
                ], 403);
            }

            DB::beginTransaction();

            foreach ($request->input('user_ids') as $userId) {
                $file->sharedWith()->syncWithoutDetaching([
                    $userId => [
                        'permissions' => $request->input('permissions'),
                        'shared_by' => $user->id,
                        'shared_at' => now(),
                        'expires_at' => $request->input('expires_at')
                    ]
                ]);

                // Notifica condivisione
                // TODO: Implementare notifica
            }

            Log::info('File shared', [
                'file_id' => $id,
                'shared_with' => $request->input('user_ids'),
                'shared_by' => $user->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'File condiviso con successo'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error sharing file', [
                'file_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella condivisione del file'
            ], 500);
        }
    }

    /**
     * Lista versioni file
     */
    public function versions($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $file = File::where('tenant_id', $tenant->id)
                ->where('is_folder', false)
                ->findOrFail($id);

            // Verifica permessi
            if (!$this->canAccessFile($file, $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            $versions = $file->versions()
                ->with('uploadedBy')
                ->orderBy('version_number', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $versions
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching file versions', [
                'file_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero delle versioni'
            ], 500);
        }
    }

    /**
     * Approva file
     */
    public function approve(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'comment' => 'nullable|string'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Verifica permessi
            if (!$user->hasPermissionTo('approve-files')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato ad approvare file'
                ], 403);
            }

            $file = File::where('tenant_id', $tenant->id)
                ->where('approval_status', 'pending')
                ->findOrFail($id);

            $file->approval_status = 'approved';
            $file->approved_by = $user->id;
            $file->approved_at = now();
            $file->approval_comment = $request->input('comment');
            $file->save();

            // Notifica approvazione
            // TODO: Implementare notifica

            Log::info('File approved', [
                'file_id' => $id,
                'approved_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File approvato con successo',
                'data' => $file
            ]);
        } catch (\Exception $e) {
            Log::error('Error approving file', [
                'file_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'approvazione del file'
            ], 500);
        }
    }

    /**
     * Rifiuta file
     */
    public function reject(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'reason' => 'required|string'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Verifica permessi
            if (!$user->hasPermissionTo('approve-files')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a rifiutare file'
                ], 403);
            }

            $file = File::where('tenant_id', $tenant->id)
                ->where('approval_status', 'pending')
                ->findOrFail($id);

            $file->approval_status = 'rejected';
            $file->approved_by = $user->id;
            $file->approved_at = now();
            $file->approval_comment = $request->input('reason');
            $file->save();

            // Notifica rifiuto
            // TODO: Implementare notifica

            Log::info('File rejected', [
                'file_id' => $id,
                'rejected_by' => $user->id,
                'reason' => $request->input('reason')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File rifiutato',
                'data' => $file
            ]);
        } catch (\Exception $e) {
            Log::error('Error rejecting file', [
                'file_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel rifiuto del file'
            ], 500);
        }
    }

    /**
     * Verifica se l'utente può accedere al file
     */
    private function canAccessFile(File $file, $user): bool
    {
        // Proprietario
        if ($file->uploaded_by === $user->id) {
            return true;
        }

        // File condiviso
        if ($file->sharedWith()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Stessa azienda
        if ($file->company_id &&
            $user->companies()->where('companies.id', $file->company_id)->exists()) {
            return true;
        }

        // Permessi globali
        return $user->hasPermissionTo('view-all-files');
    }

    /**
     * Verifica se l'utente può modificare il file
     */
    private function canEditFile(File $file, $user): bool
    {
        return $file->uploaded_by === $user->id ||
               $user->hasPermissionTo('edit-all-files');
    }

    /**
     * Verifica se l'utente può eliminare il file
     */
    private function canDeleteFile(File $file, $user): bool
    {
        return $file->uploaded_by === $user->id ||
               $user->hasPermissionTo('delete-all-files');
    }

    /**
     * Verifica se l'utente può condividere il file
     */
    private function canShareFile(File $file, $user): bool
    {
        return $file->uploaded_by === $user->id ||
               $user->hasPermissionTo('share-files');
    }

    /**
     * Ottiene breadcrumb per navigazione cartelle
     */
    private function getBreadcrumb($folderId): array
    {
        $breadcrumb = [];
        $currentFolder = File::find($folderId);

        while ($currentFolder) {
            array_unshift($breadcrumb, [
                'id' => $currentFolder->id,
                'name' => $currentFolder->name
            ]);
            $currentFolder = $currentFolder->parent;
        }

        return $breadcrumb;
    }

    /**
     * Verifica se una cartella è figlia di un'altra
     */
    private function isChildFolder($parentId, $childId): bool
    {
        $currentFolder = File::find($childId);

        while ($currentFolder) {
            if ($currentFolder->id === $parentId) {
                return true;
            }
            $currentFolder = $currentFolder->parent;
        }

        return false;
    }

    /**
     * Elimina cartella ricorsivamente
     */
    private function deleteFolder(File $folder): void
    {
        // Elimina file nella cartella
        $files = File::where('folder_id', $folder->id)->get();

        foreach ($files as $file) {
            if ($file->is_folder) {
                $this->deleteFolder($file);
            } else {
                Storage::disk('private')->delete($file->path);
                foreach ($file->versions as $version) {
                    Storage::disk('private')->delete($version->path);
                }
            }
            $file->delete();
        }
    }
}