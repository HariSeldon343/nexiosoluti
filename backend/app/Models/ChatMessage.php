<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\TenantScope;
use App\Models\Traits\HasTenant;

class ChatMessage extends Model
{
    use HasFactory, SoftDeletes, HasTenant;

    /**
     * Attributi assegnabili in massa
     */
    protected $fillable = [
        'tenant_id',
        'chat_room_id',
        'user_id',
        'parent_message_id',
        'type',
        'content',
        'attachments',
        'mentions',
        'edited_at',
        'edited_by',
        'read_by',
        'reactions',
        'metadata',
    ];

    /**
     * Attributi da castare
     */
    protected $casts = [
        'attachments' => 'array',
        'mentions' => 'array',
        'read_by' => 'array',
        'reactions' => 'array',
        'metadata' => 'array',
        'edited_at' => 'datetime',
    ];

    /**
     * Valori di default
     */
    protected $attributes = [
        'type' => 'text',
        'read_by' => '[]',
        'reactions' => '[]',
    ];

    /**
     * Boot del model
     */
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);

        static::created(function ($message) {
            // Aggiorna l'ultimo messaggio della chat room
            $message->chatRoom->update([
                'last_message_id' => $message->id,
                'last_message_at' => $message->created_at,
            ]);

            // Invia notifiche ai membri della chat
            $message->sendNotifications();

            // Broadcast del messaggio via WebSocket
            broadcast(new \App\Events\MessageSent($message))->toOthers();
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
     * Relazione con la chat room
     */
    public function chatRoom()
    {
        return $this->belongsTo(ChatRoom::class);
    }

    /**
     * Relazione con l'utente
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relazione con l'utente che ha modificato
     */
    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /**
     * Relazione con il messaggio padre (per risposte)
     */
    public function parentMessage()
    {
        return $this->belongsTo(ChatMessage::class, 'parent_message_id');
    }

    /**
     * Relazione con le risposte
     */
    public function replies()
    {
        return $this->hasMany(ChatMessage::class, 'parent_message_id');
    }

    /**
     * Marca il messaggio come letto da un utente
     */
    public function markAsReadBy($userId)
    {
        $readBy = $this->read_by ?? [];

        if (!in_array($userId, $readBy)) {
            $readBy[] = $userId;
            $this->update(['read_by' => $readBy]);
        }
    }

    /**
     * Verifica se il messaggio è stato letto da un utente
     */
    public function isReadBy($userId)
    {
        return in_array($userId, $this->read_by ?? []);
    }

    /**
     * Aggiungi una reazione
     */
    public function addReaction($userId, $emoji)
    {
        $reactions = $this->reactions ?? [];

        if (!isset($reactions[$emoji])) {
            $reactions[$emoji] = [];
        }

        if (!in_array($userId, $reactions[$emoji])) {
            $reactions[$emoji][] = $userId;
            $this->update(['reactions' => $reactions]);

            // Broadcast della reazione
            broadcast(new \App\Events\ReactionAdded($this, $userId, $emoji));
        }
    }

    /**
     * Rimuovi una reazione
     */
    public function removeReaction($userId, $emoji)
    {
        $reactions = $this->reactions ?? [];

        if (isset($reactions[$emoji])) {
            $reactions[$emoji] = array_diff($reactions[$emoji], [$userId]);

            if (empty($reactions[$emoji])) {
                unset($reactions[$emoji]);
            }

            $this->update(['reactions' => $reactions]);

            // Broadcast della rimozione reazione
            broadcast(new \App\Events\ReactionRemoved($this, $userId, $emoji));
        }
    }

    /**
     * Modifica il messaggio
     */
    public function editContent($newContent, $userId)
    {
        $this->update([
            'content' => $newContent,
            'edited_at' => now(),
            'edited_by' => $userId,
        ]);

        // Broadcast della modifica
        broadcast(new \App\Events\MessageEdited($this));
    }

    /**
     * Invia notifiche ai membri della chat
     */
    protected function sendNotifications()
    {
        if ($this->type === 'system') {
            return;
        }

        $members = $this->chatRoom->members()
            ->where('user_id', '!=', $this->user_id)
            ->wherePivot('notifications_enabled', true)
            ->get();

        foreach ($members as $member) {
            // Verifica menzioni
            $isMentioned = in_array($member->id, $this->mentions ?? []);

            // Crea notifica
            $member->notifications()->create([
                'tenant_id' => $this->tenant_id,
                'type' => $isMentioned ? 'mention' : 'message',
                'title' => $isMentioned ? 'Sei stato menzionato' : 'Nuovo messaggio',
                'body' => $this->content,
                'data' => [
                    'chat_room_id' => $this->chat_room_id,
                    'message_id' => $this->id,
                    'sender_name' => $this->user->name,
                ],
            ]);

            // Invia notifica push se abilitata
            if ($member->push_subscriptions()->active()->exists()) {
                dispatch(new \App\Jobs\SendPushNotification($member, [
                    'title' => $isMentioned ? 'Sei stato menzionato' : $this->user->name,
                    'body' => \Str::limit($this->content, 100),
                    'icon' => $this->user->avatar_url,
                    'badge' => '/images/badge.png',
                    'tag' => 'chat-' . $this->chat_room_id,
                    'data' => [
                        'type' => 'message',
                        'chat_room_id' => $this->chat_room_id,
                        'message_id' => $this->id,
                    ],
                ]));
            }
        }
    }

    /**
     * Formatta il messaggio per la visualizzazione
     */
    public function getFormattedContent()
    {
        $content = e($this->content);

        // Sostituisci menzioni con link
        if (!empty($this->mentions)) {
            $users = User::whereIn('id', $this->mentions)->get();
            foreach ($users as $user) {
                $content = str_replace(
                    '@' . $user->name,
                    '<a href="/users/' . $user->id . '" class="mention">@' . $user->name . '</a>',
                    $content
                );
            }
        }

        // Converti URL in link
        $content = preg_replace(
            '/(https?:\/\/[^\s]+)/',
            '<a href="$1" target="_blank" rel="noopener">$1</a>',
            $content
        );

        // Converti emoji shortcode
        // :smile: -> 😊
        // Implementazione dipende dalla libreria emoji utilizzata

        return $content;
    }
}