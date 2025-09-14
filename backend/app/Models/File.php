<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\TenantScope;
use App\Models\Traits\HasTenant;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class File extends Model
{
    use HasFactory, SoftDeletes, HasTenant, LogsActivity;

    /**
     * Attributi assegnabili in massa
     */
    protected $fillable = [
        'tenant_id',
        'folder_id',
        'name',
        'original_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'extension',
        'uploaded_by',
        'description',
        'tags',
        'metadata',
        'is_public',
        'is_approved',
        'approved_by',
        'approved_at',
        'approval_notes',
        'version',
        'parent_file_id',
        'checksum',
        'preview_path',
        'thumbnail_path',
        'fileable_type',
        'fileable_id',
        'expires_at',
        'password',
        'download_count',
        'last_accessed_at',
    ];

    /**
     * Attributi da castare
     */
    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'is_public' => 'boolean',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'size' => 'integer',
        'version' => 'integer',
        'download_count' => 'integer',
    ];

    /**
     * Attributi nascosti
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Valori di default
     */
    protected $attributes = [
        'is_public' => false,
        'is_approved' => false,
        'version' => 1,
        'download_count' => 0,
        'disk' => 'local',
    ];

    /**
     * Boot del model
     */
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);

        // Imposta automaticamente l'uploader
        static::creating(function ($file) {
            if (!$file->uploaded_by) {
                $file->uploaded_by = auth()->id();
            }

            // Genera checksum del file
            if ($file->path && !$file->checksum) {
                $file->checksum = md5_file(storage_path('app/' . $file->path));
            }
        });

        // Pulisci il file fisico quando viene eliminato
        static::deleted(function ($file) {
            \Storage::disk($file->disk)->delete($file->path);
            if ($file->preview_path) {
                \Storage::disk($file->disk)->delete($file->preview_path);
            }
            if ($file->thumbnail_path) {
                \Storage::disk($file->disk)->delete($file->thumbnail_path);
            }
        });
    }

    /**
     * Configurazione log attività
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'folder_id', 'is_approved', 'approved_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "File {$eventName}");
    }

    /**
     * Relazione con il tenant
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relazione con la cartella
     */
    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * Relazione con l'utente che ha caricato
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Relazione con l'utente che ha approvato
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relazione polimorfica con l'entità collegata
     */
    public function fileable()
    {
        return $this->morphTo();
    }

    /**
     * Relazione con il file padre (per versioning)
     */
    public function parentFile()
    {
        return $this->belongsTo(File::class, 'parent_file_id');
    }

    /**
     * Relazione con le versioni del file
     */
    public function versions()
    {
        return $this->hasMany(File::class, 'parent_file_id')->orderBy('version', 'desc');
    }

    /**
     * Relazione con le richieste di approvazione
     */
    public function approvalRequests()
    {
        return $this->morphMany(ApprovalRequest::class, 'approvable');
    }

    /**
     * Relazione con i permessi di condivisione
     */
    public function shares()
    {
        return $this->hasMany(FileShare::class);
    }

    /**
     * Relazione con i commenti
     */
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Scope per file pubblici
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope per file approvati
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope per file in attesa di approvazione
     */
    public function scopePendingApproval($query)
    {
        return $query->where('is_approved', false)
            ->whereHas('approvalRequests', function ($q) {
                $q->where('status', 'pending');
            });
    }

    /**
     * Scope per tipo di file
     */
    public function scopeOfType($query, $type)
    {
        $types = [
            'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'document' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'spreadsheet' => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'video' => ['video/mp4', 'video/mpeg', 'video/quicktime'],
            'audio' => ['audio/mpeg', 'audio/wav', 'audio/ogg'],
        ];

        if (isset($types[$type])) {
            return $query->whereIn('mime_type', $types[$type]);
        }

        return $query;
    }

    /**
     * Ottieni l'URL del file
     */
    public function getUrl()
    {
        if ($this->is_public) {
            return \Storage::disk($this->disk)->url($this->path);
        }

        return route('files.download', $this->id);
    }

    /**
     * Ottieni l'URL del preview
     */
    public function getPreviewUrl()
    {
        if ($this->preview_path) {
            return \Storage::disk($this->disk)->url($this->preview_path);
        }

        return null;
    }

    /**
     * Ottieni l'URL della thumbnail
     */
    public function getThumbnailUrl()
    {
        if ($this->thumbnail_path) {
            return \Storage::disk($this->disk)->url($this->thumbnail_path);
        }

        return null;
    }

    /**
     * Verifica se il file è un'immagine
     */
    public function isImage()
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Verifica se il file è un documento
     */
    public function isDocument()
    {
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
        ];

        return in_array($this->mime_type, $documentTypes);
    }

    /**
     * Crea una nuova versione del file
     */
    public function createNewVersion($path, $attributes = [])
    {
        $newVersion = $this->replicate();
        $newVersion->parent_file_id = $this->parent_file_id ?: $this->id;
        $newVersion->version = $this->version + 1;
        $newVersion->path = $path;
        $newVersion->is_approved = false;
        $newVersion->approved_by = null;
        $newVersion->approved_at = null;

        foreach ($attributes as $key => $value) {
            $newVersion->$key = $value;
        }

        $newVersion->save();

        return $newVersion;
    }

    /**
     * Approva il file
     */
    public function approve($notes = null)
    {
        $this->update([
            'is_approved' => true,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);
    }

    /**
     * Rifiuta l'approvazione
     */
    public function reject($notes = null)
    {
        $this->update([
            'is_approved' => false,
            'approval_notes' => $notes,
        ]);
    }

    /**
     * Incrementa il contatore dei download
     */
    public function incrementDownloadCount()
    {
        $this->increment('download_count');
        $this->update(['last_accessed_at' => now()]);
    }
}