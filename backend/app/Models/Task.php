<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\TenantScope;
use App\Models\Traits\HasTenant;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Task extends Model
{
    use HasFactory, SoftDeletes, HasTenant, LogsActivity;

    /**
     * Attributi assegnabili in massa
     */
    protected $fillable = [
        'tenant_id',
        'company_id',
        'title',
        'description',
        'priority',
        'status',
        'type',
        'due_date',
        'start_date',
        'completed_at',
        'created_by',
        'assigned_to',
        'parent_task_id',
        'recurrence_pattern',
        'recurrence_end_date',
        'estimated_hours',
        'actual_hours',
        'progress',
        'tags',
        'custom_fields',
        'attachments',
        'is_milestone',
        'milestone_date',
        'dependencies',
        'watchers',
        'is_private',
        'color',
        'position',
    ];

    /**
     * Attributi da castare
     */
    protected $casts = [
        'due_date' => 'datetime',
        'start_date' => 'datetime',
        'completed_at' => 'datetime',
        'recurrence_end_date' => 'datetime',
        'milestone_date' => 'datetime',
        'tags' => 'array',
        'custom_fields' => 'array',
        'attachments' => 'array',
        'dependencies' => 'array',
        'watchers' => 'array',
        'is_milestone' => 'boolean',
        'is_private' => 'boolean',
        'estimated_hours' => 'float',
        'actual_hours' => 'float',
        'progress' => 'integer',
    ];

    /**
     * Valori di default
     */
    protected $attributes = [
        'priority' => 'medium',
        'status' => 'pending',
        'type' => 'task',
        'progress' => 0,
        'is_milestone' => false,
        'is_private' => false,
    ];

    /**
     * Boot del model
     */
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);

        // Imposta automaticamente il creator
        static::creating(function ($task) {
            if (!$task->created_by) {
                $task->created_by = auth()->id();
            }
        });

        // Aggiorna completed_at quando lo stato cambia
        static::updating(function ($task) {
            if ($task->isDirty('status')) {
                if ($task->status === 'completed' && !$task->completed_at) {
                    $task->completed_at = now();
                } elseif ($task->status !== 'completed') {
                    $task->completed_at = null;
                }
            }
        });
    }

    /**
     * Configurazione log attività
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'priority', 'assigned_to', 'due_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Task {$eventName}");
    }

    /**
     * Relazione con il tenant
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relazione con l'azienda
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relazione con il creatore
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relazione con l'assegnatario
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Relazione con il task padre
     */
    public function parentTask()
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /**
     * Relazione con i subtask
     */
    public function subtasks()
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /**
     * Relazione con i commenti
     */
    public function comments()
    {
        return $this->hasMany(TaskComment::class);
    }

    /**
     * Relazione con le occorrenze (per task ricorrenti)
     */
    public function occurrences()
    {
        return $this->hasMany(TaskOccurrence::class);
    }

    /**
     * Relazione con i file allegati
     */
    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }

    /**
     * Relazione con le attività
     */
    public function activities()
    {
        return $this->morphMany(\Spatie\Activitylog\Models\Activity::class, 'subject');
    }

    /**
     * Scope per task completati
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope per task in sospeso
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope per task in corso
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope per task scaduti
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    /**
     * Scope per task assegnati a un utente
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope per priorità
     */
    public function scopePriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Verifica se il task è scaduto
     */
    public function isOverdue()
    {
        return $this->status !== 'completed'
            && $this->due_date
            && $this->due_date->isPast();
    }

    /**
     * Verifica se il task è ricorrente
     */
    public function isRecurring()
    {
        return !empty($this->recurrence_pattern);
    }

    /**
     * Calcola la prossima occorrenza per task ricorrenti
     */
    public function getNextOccurrence()
    {
        if (!$this->isRecurring()) {
            return null;
        }

        // Logica per calcolare la prossima occorrenza basata sul pattern
        // Implementazione dettagliata dipende dal formato del pattern
        return null;
    }

    /**
     * Genera occorrenze non consecutive
     */
    public function generateOccurrences($dates)
    {
        $occurrences = [];

        foreach ($dates as $date) {
            $occurrences[] = $this->occurrences()->create([
                'tenant_id' => $this->tenant_id,
                'scheduled_date' => $date,
                'status' => 'pending',
            ]);
        }

        return $occurrences;
    }
}