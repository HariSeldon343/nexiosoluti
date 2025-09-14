<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;
use App\Models\Traits\HasTenant;

class TaskOccurrence extends Model
{
    use HasFactory, HasTenant;

    /**
     * Attributi assegnabili in massa
     */
    protected $fillable = [
        'tenant_id',
        'task_id',
        'scheduled_date',
        'completed_date',
        'status',
        'notes',
        'completed_by',
    ];

    /**
     * Attributi da castare
     */
    protected $casts = [
        'scheduled_date' => 'datetime',
        'completed_date' => 'datetime',
    ];

    /**
     * Boot del model
     */
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * Relazione con il task principale
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Relazione con l'utente che ha completato
     */
    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Marca l'occorrenza come completata
     */
    public function markAsCompleted($userId = null)
    {
        $this->update([
            'status' => 'completed',
            'completed_date' => now(),
            'completed_by' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * Scope per occorrenze completate
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope per occorrenze pendenti
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope per occorrenze scadute
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('scheduled_date', '<', now());
    }
}