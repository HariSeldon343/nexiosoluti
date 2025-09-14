<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\TenantScope;
use App\Models\Traits\HasTenant;

class ChatRoom extends Model
{
    use HasFactory, SoftDeletes, HasTenant;

    /**
     * Attributi assegnabili in massa
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'type',
        'is_private',
        'avatar',
        'created_by',
        'last_message_id',
        'last_message_at',
        'settings',
        'metadata',
    ];

    /**
     * Attributi da castare
     */
    protected $casts = [
        'is_private' => 'boolean',
        'last_message_at' => 'datetime',
        'settings' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Valori di default
     */
    protected $attributes = [
        'type' => 'group',
        'is_private' => false,
    ];

    /**
     * Boot del model
     */
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($room) {
            if (!$room->created_by) {
                $room->created_by = auth()->id();
            }
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
     * Relazione con i membri
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'chat_room_members')
            ->withPivot(['role', 'joined_at', 'last_read_at', 'notifications_enabled'])
            ->withTimestamps();
    }

    /**
     * Relazione con i messaggi
     */
    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * Relazione con l'ultimo messaggio
     */
    public function lastMessage()
    {
        return $this->belongsTo(ChatMessage::class, 'last_message_id');
    }

    /**
     * Scope per chat private
     */
    public function scopePrivate($query)
    {
        return $query->where('type', 'private');
    }

    /**
     * Scope per gruppi
     */
    public function scopeGroups($query)
    {
        return $query->where('type', 'group');
    }

    /**
     * Scope per chat dirette
     */
    public function scopeDirect($query)
    {
        return $query->where('type', 'direct');
    }

    /**
     * Aggiungi un membro alla chat
     */
    public function addMember($userId, $role = 'member')
    {
        if (!$this->members()->where('user_id', $userId)->exists()) {
            $this->members()->attach($userId, [
                'role' => $role,
                'joined_at' => now(),
                'notifications_enabled' => true,
            ]);

            // Invia notifica di ingresso
            $this->messages()->create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $userId,
                'type' => 'system',
                'content' => 'è entrato nella chat',
            ]);
        }
    }

    /**
     * Rimuovi un membro dalla chat
     */
    public function removeMember($userId)
    {
        $this->members()->detach($userId);

        // Invia notifica di uscita
        $this->messages()->create([
            'tenant_id' => $this->tenant_id,
            'user_id' => $userId,
            'type' => 'system',
            'content' => 'ha lasciato la chat',
        ]);
    }

    /**
     * Verifica se un utente è membro
     */
    public function hasMember($userId)
    {
        return $this->members()->where('user_id', $userId)->exists();
    }

    /**
     * Ottieni il ruolo di un membro
     */
    public function getMemberRole($userId)
    {
        $member = $this->members()->where('user_id', $userId)->first();
        return $member ? $member->pivot->role : null;
    }

    /**
     * Verifica se un utente è admin
     */
    public function isAdmin($userId)
    {
        return $this->getMemberRole($userId) === 'admin' || $this->created_by === $userId;
    }

    /**
     * Aggiorna l'ultimo messaggio letto per un utente
     */
    public function markAsReadBy($userId)
    {
        $this->members()->updateExistingPivot($userId, [
            'last_read_at' => now(),
        ]);
    }

    /**
     * Ottieni il numero di messaggi non letti per un utente
     */
    public function getUnreadCountFor($userId)
    {
        $member = $this->members()->where('user_id', $userId)->first();

        if (!$member) {
            return 0;
        }

        $lastReadAt = $member->pivot->last_read_at;

        return $this->messages()
            ->where('user_id', '!=', $userId)
            ->when($lastReadAt, function ($query) use ($lastReadAt) {
                $query->where('created_at', '>', $lastReadAt);
            })
            ->count();
    }

    /**
     * Ottieni o crea una chat diretta tra due utenti
     */
    public static function getOrCreateDirectChat($user1Id, $user2Id)
    {
        // Cerca una chat diretta esistente tra i due utenti
        $room = static::where('type', 'direct')
            ->whereHas('members', function ($query) use ($user1Id) {
                $query->where('user_id', $user1Id);
            })
            ->whereHas('members', function ($query) use ($user2Id) {
                $query->where('user_id', $user2Id);
            })
            ->first();

        if (!$room) {
            // Crea una nuova chat diretta
            $room = static::create([
                'tenant_id' => auth()->user()->tenant_id,
                'type' => 'direct',
                'is_private' => true,
                'created_by' => $user1Id,
            ]);

            $room->addMember($user1Id);
            $room->addMember($user2Id);
        }

        return $room;
    }

    /**
     * Genera nome per chat diretta
     */
    public function getDirectChatName($forUserId)
    {
        if ($this->type !== 'direct') {
            return $this->name;
        }

        $otherUser = $this->members()->where('user_id', '!=', $forUserId)->first();
        return $otherUser ? $otherUser->name : 'Chat';
    }
}