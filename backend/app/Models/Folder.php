<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\TenantScope;
use App\Models\Traits\HasTenant;

class Folder extends Model
{
    use HasFactory, SoftDeletes, HasTenant;

    /**
     * Attributi assegnabili in massa
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'parent_id',
        'description',
        'color',
        'icon',
        'is_public',
        'is_system',
        'created_by',
        'permissions',
        'metadata',
        'position',
    ];

    /**
     * Attributi da castare
     */
    protected $casts = [
        'is_public' => 'boolean',
        'is_system' => 'boolean',
        'permissions' => 'array',
        'metadata' => 'array',
        'position' => 'integer',
    ];

    /**
     * Boot del model
     */
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($folder) {
            if (!$folder->created_by) {
                $folder->created_by = auth()->id();
            }
        });

        // Elimina ricorsivamente sottocartelle e file
        static::deleting(function ($folder) {
            $folder->children()->each(function ($child) {
                $child->delete();
            });
            $folder->files()->each(function ($file) {
                $file->delete();
            });
        });
    }

    /**
     * Relazione con il tenant
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relazione con il creatore
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relazione con la cartella padre
     */
    public function parent()
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    /**
     * Relazione con le sottocartelle
     */
    public function children()
    {
        return $this->hasMany(Folder::class, 'parent_id')->orderBy('position');
    }

    /**
     * Relazione con i file
     */
    public function files()
    {
        return $this->hasMany(File::class);
    }

    /**
     * Ottieni il percorso completo della cartella
     */
    public function getPath()
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return '/' . implode('/', $path);
    }

    /**
     * Verifica se l'utente ha permessi sulla cartella
     */
    public function userHasPermission($userId, $permission)
    {
        if ($this->is_public && $permission === 'read') {
            return true;
        }

        if ($this->created_by === $userId) {
            return true;
        }

        $permissions = $this->permissions ?? [];

        if (isset($permissions['users'][$userId])) {
            return in_array($permission, $permissions['users'][$userId]);
        }

        // Verifica permessi per ruolo
        $user = User::find($userId);
        if ($user) {
            foreach ($user->roles as $role) {
                if (isset($permissions['roles'][$role->id])) {
                    if (in_array($permission, $permissions['roles'][$role->id])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Ottieni tutti gli antenati
     */
    public function ancestors()
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Ottieni tutti i discendenti
     */
    public function descendants()
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->descendants());
        }

        return $descendants;
    }

    /**
     * Calcola la dimensione totale della cartella
     */
    public function getTotalSize()
    {
        $size = $this->files()->sum('size');

        foreach ($this->children as $child) {
            $size += $child->getTotalSize();
        }

        return $size;
    }

    /**
     * Conta il numero totale di file
     */
    public function getTotalFilesCount()
    {
        $count = $this->files()->count();

        foreach ($this->children as $child) {
            $count += $child->getTotalFilesCount();
        }

        return $count;
    }
}