<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class NotificationService
{
    protected WebPush $webPush;

    public function __construct()
    {
        // Inizializza WebPush con le chiavi VAPID
        $auth = [
            'VAPID' => [
                'subject' => config('app.url'),
                'publicKey' => config('services.webpush.vapid_public_key'),
                'privateKey' => config('services.webpush.vapid_private_key'),
            ],
        ];

        $this->webPush = new WebPush($auth);
    }

    /**
     * Invia notifica multi-canale
     */
    public function send(User $user, array $data): bool
    {
        $sent = false;

        // Salva notifica nel database (in-app)
        if ($this->shouldSendInApp($user, $data['type'] ?? 'general')) {
            $this->saveNotification($user, $data);
            $sent = true;
        }

        // Invia email
        if ($this->shouldSendEmail($user, $data['type'] ?? 'general')) {
            $this->sendEmail($user, $data);
            $sent = true;
        }

        // Invia push notification
        if ($this->shouldSendPush($user, $data['type'] ?? 'general')) {
            $this->sendPush($user, $data);
            $sent = true;
        }

        // Invia SMS (se configurato)
        if ($this->shouldSendSMS($user, $data['type'] ?? 'general')) {
            $this->sendSMS($user, $data);
            $sent = true;
        }

        return $sent;
    }

    /**
     * Salva notifica nel database
     */
    protected function saveNotification(User $user, array $data): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $data['type'] ?? 'general',
            'category' => $data['category'] ?? 'system',
            'title' => $data['title'],
            'message' => $data['body'] ?? $data['message'],
            'data' => $data['data'] ?? [],
            'action_url' => $data['action_url'] ?? null,
            'sender_id' => $data['sender_id'] ?? null
        ]);
    }

    /**
     * Invia notifica push
     */
    public function sendPush(User $user, array $data): bool
    {
        try {
            $subscriptions = PushSubscription::where('user_id', $user->id)
                ->where('is_active', true)
                ->get();

            if ($subscriptions->isEmpty()) {
                return false;
            }

            $payload = json_encode([
                'title' => $data['title'],
                'body' => $data['body'] ?? $data['message'],
                'icon' => $data['icon'] ?? '/icon-192x192.png',
                'badge' => $data['badge'] ?? '/badge-72x72.png',
                'data' => $data['data'] ?? [],
                'tag' => $data['tag'] ?? uniqid(),
                'requireInteraction' => $data['require_interaction'] ?? false,
                'actions' => $data['actions'] ?? []
            ]);

            foreach ($subscriptions as $subscription) {
                $this->sendPushNotification($subscription, json_decode($payload, true));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error sending push notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Invia notifica push a una singola sottoscrizione
     */
    public function sendPushNotification(PushSubscription $subscription, array $payload): bool
    {
        try {
            $webPushSubscription = Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token
            ]);

            $report = $this->webPush->sendOneNotification(
                $webPushSubscription,
                json_encode($payload)
            );

            if ($report->isSuccess()) {
                $subscription->last_used_at = now();
                $subscription->save();
                return true;
            } else {
                // Se la sottoscrizione non è più valida, disattivala
                if ($report->getResponse() && $report->getResponse()->getStatusCode() === 410) {
                    $subscription->is_active = false;
                    $subscription->unsubscribed_at = now();
                    $subscription->save();
                }

                Log::warning('Push notification failed', [
                    'endpoint' => $subscription->endpoint,
                    'reason' => $report->getReason()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Error sending push to subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Invia email
     */
    protected function sendEmail(User $user, array $data): bool
    {
        try {
            // TODO: Implementare template email
            // Mail::to($user->email)->send(new NotificationEmail($data));

            Log::info('Email notification would be sent', [
                'user' => $user->email,
                'subject' => $data['title']
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error sending email notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Invia SMS
     */
    protected function sendSMS(User $user, array $data): bool
    {
        try {
            if (!$user->phone) {
                return false;
            }

            // TODO: Implementare con servizio SMS (Twilio, etc.)
            Log::info('SMS notification would be sent', [
                'phone' => $user->phone,
                'message' => $data['body'] ?? $data['message']
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error sending SMS notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Verifica se inviare notifica in-app
     */
    protected function shouldSendInApp(User $user, string $type): bool
    {
        $settings = $user->notification_settings['in_app'] ?? [];
        return $settings[$type] ?? true;
    }

    /**
     * Verifica se inviare email
     */
    protected function shouldSendEmail(User $user, string $type): bool
    {
        $settings = $user->notification_settings['email'] ?? [];

        // Verifica quiet hours
        if ($this->isInQuietHours($user)) {
            return false;
        }

        return $settings[$type] ?? false;
    }

    /**
     * Verifica se inviare push
     */
    protected function shouldSendPush(User $user, string $type): bool
    {
        $settings = $user->notification_settings['push'] ?? [];

        // Verifica quiet hours
        if ($this->isInQuietHours($user)) {
            return false;
        }

        // Verifica se l'utente ha sottoscrizioni attive
        $hasActiveSubscriptions = PushSubscription::where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        return $hasActiveSubscriptions && ($settings[$type] ?? true);
    }

    /**
     * Verifica se inviare SMS
     */
    protected function shouldSendSMS(User $user, string $type): bool
    {
        $settings = $user->notification_settings['sms'] ?? [];

        // Verifica quiet hours
        if ($this->isInQuietHours($user)) {
            return false;
        }

        return $user->phone && ($settings[$type] ?? false);
    }

    /**
     * Verifica se siamo in quiet hours per l'utente
     */
    protected function isInQuietHours(User $user): bool
    {
        $quietHours = $user->notification_settings['quiet_hours'] ?? [];

        if (!($quietHours['enabled'] ?? false)) {
            return false;
        }

        $timezone = $quietHours['timezone'] ?? 'Europe/Rome';
        $now = now()->setTimezone($timezone);

        $start = \Carbon\Carbon::parse($quietHours['start'] ?? '22:00', $timezone);
        $end = \Carbon\Carbon::parse($quietHours['end'] ?? '08:00', $timezone);

        // Se l'orario di fine è prima dell'inizio, significa che attraversa la mezzanotte
        if ($end < $start) {
            return $now >= $start || $now <= $end;
        }

        return $now >= $start && $now <= $end;
    }

    /**
     * Invia notifica di gruppo
     */
    public function sendToGroup(array $users, array $data): array
    {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'users' => []
        ];

        foreach ($users as $user) {
            if ($this->send($user, $data)) {
                $results['sent']++;
                $results['users'][] = [
                    'id' => $user->id,
                    'status' => 'sent'
                ];
            } else {
                $results['failed']++;
                $results['users'][] = [
                    'id' => $user->id,
                    'status' => 'failed'
                ];
            }
        }

        return $results;
    }

    /**
     * Invia notifica broadcast a tutti gli utenti del tenant
     */
    public function broadcast(int $tenantId, array $data, array $filters = []): array
    {
        $query = User::where('tenant_id', $tenantId)
            ->where('is_active', true);

        // Applica filtri
        if (!empty($filters['roles'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->whereIn('name', $filters['roles']);
            });
        }

        if (!empty($filters['companies'])) {
            $query->whereHas('companies', function ($q) use ($filters) {
                $q->whereIn('companies.id', $filters['companies']);
            });
        }

        $users = $query->get();

        return $this->sendToGroup($users, $data);
    }

    /**
     * Pianifica notifica
     */
    public function schedule(User $user, array $data, \DateTime $sendAt): bool
    {
        // TODO: Implementare con job queue
        // dispatch(new SendNotificationJob($user, $data))->delay($sendAt);

        return true;
    }

    /**
     * Invia promemoria
     */
    public function sendReminder(User $user, string $type, array $data): bool
    {
        $reminderData = array_merge($data, [
            'type' => 'reminder',
            'category' => $type,
            'title' => $data['title'] ?? 'Promemoria',
            'icon' => '/icons/reminder.png'
        ]);

        return $this->send($user, $reminderData);
    }

    /**
     * Invia digest giornaliero
     */
    public function sendDailyDigest(User $user): bool
    {
        // Raccogli attività del giorno
        $activities = $this->getDailyActivities($user);

        if (empty($activities)) {
            return false;
        }

        $data = [
            'type' => 'daily_digest',
            'category' => 'system',
            'title' => 'Riepilogo giornaliero',
            'body' => $this->formatDigestMessage($activities),
            'data' => $activities
        ];

        // Invia solo via email
        return $this->sendEmail($user, $data);
    }

    /**
     * Ottieni attività giornaliere per l'utente
     */
    protected function getDailyActivities(User $user): array
    {
        return [
            'tasks_due' => $user->tasks()
                ->where('due_date', today())
                ->count(),
            'events_today' => $user->events()
                ->whereDate('start_date', today())
                ->count(),
            'unread_messages' => $user->unreadMessages()
                ->count(),
            'pending_approvals' => $user->pendingApprovals()
                ->count()
        ];
    }

    /**
     * Formatta messaggio digest
     */
    protected function formatDigestMessage(array $activities): string
    {
        $parts = [];

        if ($activities['tasks_due'] > 0) {
            $parts[] = "{$activities['tasks_due']} task in scadenza";
        }

        if ($activities['events_today'] > 0) {
            $parts[] = "{$activities['events_today']} eventi oggi";
        }

        if ($activities['unread_messages'] > 0) {
            $parts[] = "{$activities['unread_messages']} messaggi non letti";
        }

        if ($activities['pending_approvals'] > 0) {
            $parts[] = "{$activities['pending_approvals']} approvazioni in attesa";
        }

        return implode(', ', $parts);
    }
}