<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Models\ChatRoom;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\TenantService;
use App\Events\MessageSent;
use App\Events\UserTyping;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Lista chat rooms
     */
    public function rooms(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $query = ChatRoom::where('tenant_id', $tenant->id)
                ->whereHas('participants', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->with(['lastMessage', 'participants' => function ($q) use ($user) {
                    $q->where('user_id', '!=', $user->id)->limit(5);
                }]);

            // Filtro per tipo
            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
            }

            // Ricerca
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', "%{$search}%");
            }

            // Solo room con messaggi non letti
            if ($request->boolean('unread_only')) {
                $query->whereHas('messages', function ($q) use ($user) {
                    $q->whereDoesntHave('readBy', function ($q2) use ($user) {
                        $q2->where('user_id', $user->id);
                    });
                });
            }

            // Ordinamento per ultima attività
            $query->orderBy('last_activity_at', 'desc');

            $rooms = $query->get();

            // Aggiungi contatori per ogni room
            $rooms->transform(function ($room) use ($user) {
                // Conteggio messaggi non letti
                $room->unread_count = $room->messages()
                    ->whereDoesntHave('readBy', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                    ->where('user_id', '!=', $user->id)
                    ->count();

                // Partecipanti online
                $room->online_count = $room->participants()
                    ->where('last_activity_at', '>', now()->subMinutes(5))
                    ->count();

                // Per chat dirette, aggiungi info altro utente
                if ($room->type === 'direct') {
                    $otherUser = $room->participants()
                        ->where('user_id', '!=', $user->id)
                        ->first();

                    if ($otherUser) {
                        $room->other_user = [
                            'id' => $otherUser->id,
                            'name' => $otherUser->name,
                            'avatar' => $otherUser->avatar,
                            'is_online' => $otherUser->last_activity_at &&
                                         $otherUser->last_activity_at->gt(now()->subMinutes(5))
                        ];
                    }
                }

                return $room;
            });

            Log::info('Chat rooms retrieved', [
                'user_id' => $user->id,
                'rooms_count' => $rooms->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $rooms
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching chat rooms', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero delle chat'
            ], 500);
        }
    }

    /**
     * Messaggi di una chat room
     */
    public function messages(Request $request, $roomId): JsonResponse
    {
        try {
            $request->validate([
                'before_id' => 'nullable|exists:chat_messages,id',
                'limit' => 'nullable|integer|min:1|max:100'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $room = ChatRoom::where('tenant_id', $tenant->id)
                ->findOrFail($roomId);

            // Verifica che l'utente sia partecipante
            if (!$room->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            $query = $room->messages()
                ->with(['user', 'attachments', 'readBy']);

            // Paginazione inversa per caricamento storico
            if ($request->has('before_id')) {
                $query->where('id', '<', $request->input('before_id'));
            }

            $limit = $request->input('limit', 50);
            $messages = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();

            // Segna messaggi come letti
            $unreadMessageIds = $messages->filter(function ($message) use ($user) {
                return !$message->readBy->contains('id', $user->id) &&
                       $message->user_id !== $user->id;
            })->pluck('id');

            if ($unreadMessageIds->isNotEmpty()) {
                DB::table('chat_message_reads')->insert(
                    $unreadMessageIds->map(function ($messageId) use ($user) {
                        return [
                            'message_id' => $messageId,
                            'user_id' => $user->id,
                            'read_at' => now()
                        ];
                    })->toArray()
                );

                // Aggiorna contatore non letti della room
                $room->participants()
                    ->where('user_id', $user->id)
                    ->update(['unread_count' => 0]);
            }

            // Aggiungi informazioni extra ai messaggi
            $messages->transform(function ($message) use ($user) {
                $message->is_mine = $message->user_id === $user->id;
                $message->is_read = $message->readBy->contains('id', $user->id) ||
                                   $message->user_id === $user->id;
                return $message;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $messages,
                    'has_more' => $messages->count() === $limit
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching messages', [
                'room_id' => $roomId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero dei messaggi'
            ], 500);
        }
    }

    /**
     * Crea chat room
     */
    public function createRoom(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required_if:type,group|string|max:255',
                'type' => 'required|in:direct,group,channel',
                'participant_ids' => 'required|array|min:1',
                'participant_ids.*' => 'exists:users,id',
                'description' => 'nullable|string',
                'is_public' => 'boolean'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Per chat dirette, verifica che ci sia solo un altro partecipante
            if ($request->input('type') === 'direct') {
                if (count($request->input('participant_ids')) !== 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le chat dirette devono avere esattamente un altro partecipante'
                    ], 422);
                }

                // Verifica se esiste già una chat diretta
                $otherUserId = $request->input('participant_ids')[0];
                $existingRoom = ChatRoom::where('tenant_id', $tenant->id)
                    ->where('type', 'direct')
                    ->whereHas('participants', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                    ->whereHas('participants', function ($q) use ($otherUserId) {
                        $q->where('user_id', $otherUserId);
                    })
                    ->first();

                if ($existingRoom) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Chat esistente',
                        'data' => $existingRoom
                    ]);
                }
            }

            DB::beginTransaction();

            // Crea room
            $room = ChatRoom::create([
                'tenant_id' => $tenant->id,
                'name' => $request->input('name') ?? $this->generateRoomName(
                    $request->input('type'),
                    $request->input('participant_ids'),
                    $user
                ),
                'type' => $request->input('type'),
                'description' => $request->input('description'),
                'is_public' => $request->boolean('is_public'),
                'created_by' => $user->id,
                'last_activity_at' => now()
            ]);

            // Aggiungi creatore come partecipante
            $room->participants()->attach($user->id, [
                'role' => 'admin',
                'joined_at' => now()
            ]);

            // Aggiungi altri partecipanti
            foreach ($request->input('participant_ids') as $participantId) {
                // Verifica che l'utente appartenga al tenant
                $participant = User::where('tenant_id', $tenant->id)
                    ->findOrFail($participantId);

                $room->participants()->attach($participantId, [
                    'role' => 'member',
                    'joined_at' => now()
                ]);
            }

            // Messaggio di sistema per creazione room
            if ($request->input('type') === 'group') {
                $room->messages()->create([
                    'user_id' => $user->id,
                    'message' => "{$user->name} ha creato il gruppo",
                    'type' => 'system'
                ]);
            }

            // Log creazione
            Log::info('Chat room created', [
                'room_id' => $room->id,
                'type' => $room->type,
                'created_by' => $user->id
            ]);

            DB::commit();

            // Carica relazioni
            $room->load(['participants', 'lastMessage']);

            return response()->json([
                'success' => true,
                'message' => 'Chat creata con successo',
                'data' => $room
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating chat room', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella creazione della chat'
            ], 500);
        }
    }

    /**
     * Invia messaggio
     */
    public function sendMessage(SendMessageRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $room = ChatRoom::where('tenant_id', $tenant->id)
                ->findOrFail($request->input('room_id'));

            // Verifica che l'utente sia partecipante
            if (!$room->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a inviare messaggi in questa chat'
                ], 403);
            }

            DB::beginTransaction();

            // Crea messaggio
            $message = $room->messages()->create([
                'user_id' => $user->id,
                'message' => $request->input('message'),
                'type' => $request->input('type', 'text'),
                'metadata' => $request->input('metadata')
            ]);

            // Gestione allegati
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store("chat/{$room->id}", 'private');

                    $message->attachments()->create([
                        'filename' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType()
                    ]);
                }
            }

            // Aggiorna ultima attività della room
            $room->last_activity_at = now();
            $room->last_message_id = $message->id;
            $room->save();

            // Incrementa contatore non letti per altri partecipanti
            $room->participants()
                ->where('user_id', '!=', $user->id)
                ->increment('unread_count');

            // Log invio messaggio
            Log::info('Chat message sent', [
                'message_id' => $message->id,
                'room_id' => $room->id,
                'user_id' => $user->id
            ]);

            DB::commit();

            // Carica relazioni
            $message->load(['user', 'attachments']);

            // Broadcast messaggio via WebSocket
            broadcast(new MessageSent($message, $room))->toOthers();

            // Invia notifiche push agli utenti offline
            $this->sendPushNotifications($room, $message, $user);

            return response()->json([
                'success' => true,
                'message' => 'Messaggio inviato',
                'data' => $message
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error sending message', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'invio del messaggio'
            ], 500);
        }
    }

    /**
     * Segna messaggi come letti
     */
    public function markAsRead(Request $request, $roomId): JsonResponse
    {
        try {
            $request->validate([
                'message_ids' => 'array',
                'message_ids.*' => 'exists:chat_messages,id'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $room = ChatRoom::where('tenant_id', $tenant->id)
                ->findOrFail($roomId);

            // Verifica che l'utente sia partecipante
            if (!$room->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            if ($request->has('message_ids')) {
                // Segna messaggi specifici come letti
                $messageIds = $request->input('message_ids');
            } else {
                // Segna tutti i messaggi non letti
                $messageIds = $room->messages()
                    ->whereDoesntHave('readBy', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                    ->where('user_id', '!=', $user->id)
                    ->pluck('id');
            }

            if (count($messageIds) > 0) {
                $readData = collect($messageIds)->map(function ($messageId) use ($user) {
                    return [
                        'message_id' => $messageId,
                        'user_id' => $user->id,
                        'read_at' => now()
                    ];
                })->toArray();

                DB::table('chat_message_reads')->insertOrIgnore($readData);

                // Resetta contatore non letti
                $room->participants()
                    ->where('user_id', $user->id)
                    ->update(['unread_count' => 0]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Messaggi segnati come letti'
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking messages as read', [
                'room_id' => $roomId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel segnare i messaggi come letti'
            ], 500);
        }
    }

    /**
     * Indicatore di digitazione
     */
    public function typing(Request $request, $roomId): JsonResponse
    {
        try {
            $request->validate([
                'is_typing' => 'required|boolean'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $room = ChatRoom::where('tenant_id', $tenant->id)
                ->findOrFail($roomId);

            // Verifica che l'utente sia partecipante
            if (!$room->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            // Broadcast evento typing via WebSocket
            broadcast(new UserTyping(
                $user,
                $room,
                $request->boolean('is_typing')
            ))->toOthers();

            return response()->json([
                'success' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Error broadcasting typing indicator', [
                'room_id' => $roomId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'invio dell\'indicatore di digitazione'
            ], 500);
        }
    }

    /**
     * Genera nome room per chat dirette
     */
    private function generateRoomName($type, $participantIds, $currentUser)
    {
        if ($type === 'direct') {
            $otherUser = User::find($participantIds[0]);
            return $otherUser ? $otherUser->name : 'Chat Diretta';
        }

        return 'Nuovo Gruppo';
    }

    /**
     * Invia notifiche push agli utenti offline
     */
    private function sendPushNotifications($room, $message, $sender)
    {
        try {
            $offlineUsers = $room->participants()
                ->where('user_id', '!=', $sender->id)
                ->where(function ($q) {
                    $q->whereNull('last_activity_at')
                      ->orWhere('last_activity_at', '<', now()->subMinutes(5));
                })
                ->get();

            foreach ($offlineUsers as $user) {
                // TODO: Implementare invio notifica push
                // $this->notificationService->sendPush($user, [
                //     'title' => $room->name ?? $sender->name,
                //     'body' => Str::limit($message->message, 100),
                //     'data' => [
                //         'type' => 'chat_message',
                //         'room_id' => $room->id,
                //         'message_id' => $message->id
                //     ]
                // ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to send push notifications', [
                'error' => $e->getMessage()
            ]);
        }
    }
}