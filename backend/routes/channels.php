<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private user channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Tenant channel
Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    return $user->tenant_id === $tenantId;
});

// Company channel
Broadcast::channel('company.{companyId}', function ($user, $companyId) {
    return $user->companies->contains($companyId);
});

// Chat channel
Broadcast::channel('chat.{channelId}', function ($user, $channelId) {
    // Check if user is member of the chat channel
    return \App\Models\ChatChannelMember::where('channel_id', $channelId)
        ->where('user_id', $user->id)
        ->exists();
});

// Presence channel for online users
Broadcast::channel('presence.tenant.{tenantId}', function ($user, $tenantId) {
    if ($user->tenant_id === $tenantId) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar_url,
        ];
    }
});

// Task updates channel
Broadcast::channel('tasks.{taskId}', function ($user, $taskId) {
    $task = \App\Models\Task::find($taskId);
    return $task && ($task->assignee_id === $user->id || $task->created_by === $user->id);
});

// Calendar events channel
Broadcast::channel('calendar.{calendarId}', function ($user, $calendarId) {
    return \App\Models\CalendarUser::where('calendar_id', $calendarId)
        ->where('user_id', $user->id)
        ->exists();
});