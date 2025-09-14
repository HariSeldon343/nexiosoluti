<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\PushSubscription;
use App\Services\TenantService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    protected TenantService $tenantService;
    protected NotificationService $notificationService;

    public function __construct(
        TenantService $tenantService,
        NotificationService $notificationService
    ) {
        $this->tenantService = $tenantService;
        $this->notificationService = $notificationService;
    }

    /**
     * Lista notifiche utente
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $query = $user->notifications()
                ->with('sender');

            // Filtri
            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->has('category')) {
                $query->where('category', $request->input('category'));
            }

            if ($request->boolean('unread_only')) {
                $query->whereNull('read_at');
            }

            // Ordinamento
            $query->orderBy('created_at', 'desc');

            // Paginazione
            $perPage = $request->input('per_page', 20);
            $notifications = $query->paginate($perPage);

            // Aggiungi informazioni extra
            $notifications->getCollection()->transform(function ($notification) {
                $notification->is_read = !is_null($notification->read_at);
                $notification->time_ago = $notification->created_at->diffForHumans();

                // Formatta data in base al tipo
                switch ($notification->type) {
                    case 'task_assigned':
                        $notification->icon = 'task';
                        $notification->color = 'blue';
                        break;
                    case 'file_shared':
                        $notification->icon = 'file';
                        $notification->color = 'green';
                        break;
                    case 'mention':
                        $notification->icon = 'at';
                        $notification->color = 'purple';
                        break;
                    case 'event_reminder':
                        $notification->icon = 'calendar';
                        $notification->color = 'orange';
                        break;
                    case 'approval_request':
                        $notification->icon = 'check-circle';
                        $notification->color = 'yellow';
                        break;
                    default:
                        $notification->icon = 'bell';
                        $notification->color = 'gray';
                }

                return $notification;
            });

            // Conta notifiche non lette totali
            $unreadCount = $user->notifications()
                ->whereNull('read_at')
                ->count();

            // Conta per categoria
            $categoryCounts = $user->notifications()
                ->whereNull('read_at')
                ->select('category', DB::raw('count(*) as count'))
                ->groupBy('category')
                ->pluck('count', 'category');

            Log::info('Notifications retrieved', [
                'user_id' => $user->id,
                'count' => $notifications->total()
            ]);

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'meta' => [
                    'unread_count' => $unreadCount,
                    'category_counts' => $categoryCounts
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching notifications', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero delle notifiche'
            ], 500);
        }
    }

    /**
     * Segna notifica come letta
     */
    public function markAsRead($id): JsonResponse
    {
        try {
            $user = Auth::user();

            $notification = $user->notifications()->findOrFail($id);

            if (!$notification->read_at) {
                $notification->read_at = now();
                $notification->save();

                Log::info('Notification marked as read', [
                    'notification_id' => $id,
                    'user_id' => $user->id
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notifica segnata come letta'
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking notification as read', [
                'notification_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel segnare la notifica come letta'
            ], 500);
        }
    }

    /**
     * Segna tutte le notifiche come lette
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $query = $user->notifications()->whereNull('read_at');

            // Opzionale: filtra per categoria
            if ($request->has('category')) {
                $query->where('category', $request->input('category'));
            }

            // Opzionale: filtra per tipo
            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
            }

            $updatedCount = $query->update(['read_at' => now()]);

            Log::info('All notifications marked as read', [
                'user_id' => $user->id,
                'count' => $updatedCount
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$updatedCount} notifiche segnate come lette"
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel segnare le notifiche come lette'
            ], 500);
        }
    }

    /**
     * Sottoscrizione push notifications
     */
    public function subscribe(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'endpoint' => 'required|url',
                'keys' => 'required|array',
                'keys.p256dh' => 'required|string',
                'keys.auth' => 'required|string',
                'device_type' => 'nullable|in:web,mobile,desktop',
                'device_name' => 'nullable|string'
            ]);

            $user = Auth::user();

            // Verifica se esiste già una sottoscrizione per questo endpoint
            $subscription = PushSubscription::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'endpoint' => $request->input('endpoint')
                ],
                [
                    'public_key' => $request->input('keys.p256dh'),
                    'auth_token' => $request->input('keys.auth'),
                    'device_type' => $request->input('device_type', 'web'),
                    'device_name' => $request->input('device_name'),
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                    'is_active' => true,
                    'last_used_at' => now()
                ]
            );

            // Test invio notifica di conferma
            $this->notificationService->sendPushNotification($subscription, [
                'title' => 'Notifiche attivate',
                'body' => 'Riceverai notifiche push su questo dispositivo',
                'icon' => '/icon-192x192.png',
                'badge' => '/badge-72x72.png',
                'data' => [
                    'type' => 'subscription_confirmed'
                ]
            ]);

            Log::info('Push subscription created/updated', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'device_type' => $subscription->device_type
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sottoscrizione push attivata con successo',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'device_type' => $subscription->device_type
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error subscribing to push notifications', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella sottoscrizione alle notifiche push'
            ], 500);
        }
    }

    /**
     * Rimuovi sottoscrizione push
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'endpoint' => 'required|url'
            ]);

            $user = Auth::user();

            $subscription = PushSubscription::where('user_id', $user->id)
                ->where('endpoint', $request->input('endpoint'))
                ->first();

            if ($subscription) {
                $subscription->is_active = false;
                $subscription->unsubscribed_at = now();
                $subscription->save();

                Log::info('Push subscription deactivated', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $user->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Sottoscrizione rimossa con successo'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Sottoscrizione non trovata'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error unsubscribing from push notifications', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella rimozione della sottoscrizione'
            ], 500);
        }
    }

    /**
     * Impostazioni notifiche utente
     */
    public function settings(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($request->isMethod('GET')) {
                // Recupera impostazioni correnti
                $settings = $user->notification_settings ?? $this->getDefaultSettings();

                // Aggiungi info dispositivi con push attivo
                $activeDevices = PushSubscription::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->select(['device_type', 'device_name', 'last_used_at'])
                    ->get();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'settings' => $settings,
                        'active_devices' => $activeDevices
                    ]
                ]);
            }

            // Aggiorna impostazioni
            $request->validate([
                'settings' => 'required|array',
                'settings.email' => 'array',
                'settings.push' => 'array',
                'settings.in_app' => 'array'
            ]);

            $user->notification_settings = array_merge(
                $user->notification_settings ?? $this->getDefaultSettings(),
                $request->input('settings')
            );
            $user->save();

            Log::info('Notification settings updated', [
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Impostazioni notifiche aggiornate',
                'data' => $user->notification_settings
            ]);
        } catch (\Exception $e) {
            Log::error('Error managing notification settings', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella gestione delle impostazioni'
            ], 500);
        }
    }

    /**
     * Test invio notifica
     */
    public function test(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'channel' => 'required|in:email,push,sms',
                'title' => 'required|string',
                'message' => 'required|string'
            ]);

            $user = Auth::user();

            // Invia notifica di test
            $result = $this->notificationService->send($user, [
                'channel' => $request->input('channel'),
                'title' => $request->input('title'),
                'body' => $request->input('message'),
                'type' => 'test',
                'category' => 'system'
            ]);

            if ($result) {
                Log::info('Test notification sent', [
                    'user_id' => $user->id,
                    'channel' => $request->input('channel')
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Notifica di test inviata con successo'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Impossibile inviare la notifica di test'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error sending test notification', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'invio della notifica di test'
            ], 500);
        }
    }

    /**
     * Ottieni impostazioni di default per le notifiche
     */
    private function getDefaultSettings(): array
    {
        return [
            'email' => [
                'task_assigned' => true,
                'task_completed' => false,
                'task_comment' => true,
                'file_shared' => true,
                'mention' => true,
                'event_reminder' => true,
                'approval_request' => true,
                'daily_summary' => false
            ],
            'push' => [
                'task_assigned' => true,
                'task_completed' => false,
                'task_comment' => true,
                'file_shared' => true,
                'mention' => true,
                'event_reminder' => true,
                'approval_request' => true,
                'chat_message' => true
            ],
            'in_app' => [
                'task_assigned' => true,
                'task_completed' => true,
                'task_comment' => true,
                'file_shared' => true,
                'mention' => true,
                'event_reminder' => true,
                'approval_request' => true,
                'chat_message' => true,
                'system_update' => true
            ],
            'quiet_hours' => [
                'enabled' => false,
                'start' => '22:00',
                'end' => '08:00',
                'timezone' => 'Europe/Rome'
            ]
        ];
    }
}