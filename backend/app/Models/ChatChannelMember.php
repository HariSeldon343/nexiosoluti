<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatChannelMember extends Model
{
    protected $table = 'chat_channel_members';

    protected $fillable = [
        'channel_id',
        'user_id',
        'role',
        'joined_at',
        'last_read_at',
        'notifications_enabled',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'last_read_at' => 'datetime',
        'notifications_enabled' => 'boolean',
    ];

    /**
     * Get the channel
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'channel_id');
    }

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}