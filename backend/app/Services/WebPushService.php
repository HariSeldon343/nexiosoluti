<?php

namespace App\Services;

use App\Models\User;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;

/**
 * Servizio per Web Push Notifications
 * Gestisce subscriptions e invio notifiche push tramite VAPID
 */
class WebPushService
{
    protected $webPush;
    protected $vapidPublicKey;
    protected $vapidPrivateKey;

    public function __construct()
    {
        $this->vapidPublicKey = config('services.webpush.public_key', env('VAPID_PUBLIC_KEY'));
        $this->vapidPrivateKey = config('services.webpush.private_key', env('VAPID_PRIVATE_KEY'));

        // Genera chiavi VAPID se non esistono
        if (!$this->vapidPublicKey || !$this->vapidPrivateKey) {
            $this->generateVAPIDKeys();
        }

        // Inizializza WebPush
        $auth = [
            'VAPID' => [
                'subject' => config('app.url'),
                'publicKey' => $this->vapidPublicKey,
                'privateKey' => $this->vapidPrivateKey,
            ],
        ];

        $this->webPush = new WebPush($auth);

        // Configurazioni aggiuntive
        $this->webPush->setDefaultOptions([
            'TTL' => 86400, // 24 ore
            'urgency' => 'normal',
            'topic' => 'nexiosolution',
            'batchSize' => 200,
        ]);
    }

    /**
     * Genera nuove chiavi VAPID
     */
    public function generateVAPIDKeys(): array
    {
        $keys = VAPID::createVapidKeys();

        // Salva le chiavi nel file .env
        $this->updateEnvFile([
            'VAPID_PUBLIC_KEY' => $keys['publicKey'],
            'VAPID_PRIVATE_KEY' => $keys['privateKey'],
        ]);

        $this->vapidPublicKey = $keys['publicKey'];
        $this->vapidPrivateKey = $keys['privateKey'];

        Log::info('VAPID keys generated successfully');

        return $keys;
    }

    /**
     * Ottiene la chiave pubblica VAPID per il client
     */
    public function getPublicKey(): string
    {
        return $this->vapidPublicKey;
    }

    /**
     * Registra una nuova subscription
     */
    public function subscribe(User $user, array $subscriptionData): PushSubscription
    {
        // Valida i dati della subscription
        $this->validateSubscription($subscriptionData);

        // Verifica se esiste già una subscription per questo endpoint
        $existingSubscription = PushSubscription::where('endpoint', $subscriptionData['endpoint'])->first();

        if ($existingSubscription) {
            // Aggiorna la subscription esistente
            $existingSubscription->update([
                'user_id' => $user->id,
                'p256dh' => $subscriptionData['keys']['p256dh'],
                'auth' => $subscriptionData['keys']['auth'],
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
                'last_used_at' => now(),
            ]);

            return $existingSubscription;
        }

        // Crea nuova subscription
        $subscription = PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => $subscriptionData['endpoint'],
            'p256dh' => $subscriptionData['keys']['p256dh'],
            'auth' => $subscriptionData['keys']['auth'],
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
            'last_used_at' => now(),
        ]);

        Log::info('Push subscription created', [
            'user_id' => $user->id,
            'endpoint' => substr($subscriptionData['endpoint'], 0, 50) . '...',
        ]);

        // Invia notifica di benvenuto
        $this->sendWelcomeNotification($subscription);

        return $subscription;
    }

    /**
     * Rimuove una subscription
     */
    public function unsubscribe(string $endpoint): bool
    {
        $subscription = PushSubscription::where('endpoint', $endpoint)->first();

        if ($subscription) {
            $subscription->delete();
            Log::info('Push subscription removed', ['endpoint' => substr($endpoint, 0, 50) . '...']);
            return true;
        }

        return false;
    }

    /**
     * Invia notifica a un singolo utente
     */
    public function sendToUser(User $user, array $notification): array
    {
        $subscriptions = $user->pushSubscriptions()->active()->get();

        if ($subscriptions->isEmpty()) {
            Log::warning('No active push subscriptions for user', ['user_id' => $user->id]);
            return ['sent' => 0, 'failed' => 0];
        }

        $results = ['sent' => 0, 'failed' => 0];

        foreach ($subscriptions as $subscription) {
            $result = $this->sendNotification($subscription, $notification);

            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Invia notifica a multiple subscriptions
     */
    public function sendToMultiple(array $userIds, array $notification): array
    {
        $subscriptions = PushSubscription::whereIn('user_id', $userIds)
            ->active()
            ->get();

        $results = ['sent' => 0, 'failed' => 0];

        foreach ($subscriptions as $subscription) {
            $result = $this->sendNotification($subscription, $notification);

            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Invia notifica broadcast a tutti gli utenti
     */
    public function broadcast(array $notification): array
    {
        $subscriptions = PushSubscription::active()->get();

        $results = ['sent' => 0, 'failed' => 0];

        // Invia in batch per migliori performance
        foreach ($subscriptions->chunk(100) as $batch) {
            foreach ($batch as $subscription) {
                $this->queueNotification($subscription, $notification);
            }

            // Flush del batch
            $batchResults = $this->flushQueue();
            $results['sent'] += $batchResults['sent'];
            $results['failed'] += $batchResults['failed'];
        }

        return $results;
    }

    /**
     * Invia una singola notifica
     */
    protected function sendNotification(PushSubscription $pushSubscription, array $notification): array
    {
        try {
            // Prepara il payload
            $payload = $this->preparePayload($notification);

            // Crea oggetto Subscription per la libreria WebPush
            $subscription = Subscription::create([
                'endpoint' => $pushSubscription->endpoint,
                'publicKey' => $pushSubscription->p256dh,
                'authToken' => $pushSubscription->auth,
            ]);

            // Invia la notifica
            $report = $this->webPush->sendOneNotification(
                $subscription,
                json_encode($payload)
            );

            // Verifica il risultato
            if ($report->isSuccess()) {
                $pushSubscription->update([
                    'last_used_at' => now(),
                    'failure_count' => 0,
                ]);

                return ['success' => true];
            } else {
                // Gestisci errore
                $this->handleFailure($pushSubscription, $report);

                return [
                    'success' => false,
                    'error' => $report->getReason(),
                    'statusCode' => $report->getResponse()->getStatusCode(),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error sending push notification', [
                'subscription_id' => $pushSubscription->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Accoda notifica per invio batch
     */
    protected function queueNotification(PushSubscription $pushSubscription, array $notification): void
    {
        $payload = $this->preparePayload($notification);

        $subscription = Subscription::create([
            'endpoint' => $pushSubscription->endpoint,
            'publicKey' => $pushSubscription->p256dh,
            'authToken' => $pushSubscription->auth,
        ]);

        $this->webPush->queueNotification($subscription, json_encode($payload));
    }

    /**
     * Invia tutte le notifiche in coda
     */
    protected function flushQueue(): array
    {
        $results = ['sent' => 0, 'failed' => 0];

        foreach ($this->webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $results['sent']++;
            } else {
                $results['failed']++;

                // Log errori
                Log::warning('Push notification failed', [
                    'endpoint' => substr($report->getEndpoint(), 0, 50) . '...',
                    'reason' => $report->getReason(),
                    'statusCode' => $report->getResponse() ? $report->getResponse()->getStatusCode() : null,
                ]);
            }
        }

        return $results;
    }

    /**
     * Prepara il payload della notifica
     */
    protected function preparePayload(array $notification): array
    {
        $payload = [
            'title' => $notification['title'] ?? 'NexioSolution',
            'body' => $notification['body'] ?? '',
            'icon' => $notification['icon'] ?? '/images/notification-icon.png',
            'badge' => $notification['badge'] ?? '/images/notification-badge.png',
            'image' => $notification['image'] ?? null,
            'tag' => $notification['tag'] ?? uniqid(),
            'renotify' => $notification['renotify'] ?? false,
            'requireInteraction' => $notification['requireInteraction'] ?? false,
            'silent' => $notification['silent'] ?? false,
            'timestamp' => $notification['timestamp'] ?? time() * 1000,
            'vibrate' => $notification['vibrate'] ?? [200, 100, 200],
            'data' => $notification['data'] ?? [],
        ];

        // Aggiungi azioni se presenti
        if (isset($notification['actions']) && is_array($notification['actions'])) {
            $payload['actions'] = $notification['actions'];
        }

        // Aggiungi URL se presente
        if (isset($notification['url'])) {
            $payload['data']['url'] = $notification['url'];
        }

        // Aggiungi tipo notifica
        if (isset($notification['type'])) {
            $payload['data']['type'] = $notification['type'];
        }

        return $payload;
    }

    /**
     * Gestisce il fallimento di una notifica
     */
    protected function handleFailure(PushSubscription $subscription, $report): void
    {
        $statusCode = $report->getResponse() ? $report->getResponse()->getStatusCode() : null;

        // Incrementa contatore fallimenti
        $subscription->increment('failure_count');

        // Se il codice è 410 (Gone), rimuovi la subscription
        if ($statusCode === 410) {
            $subscription->delete();
            Log::info('Removed invalid push subscription', [
                'subscription_id' => $subscription->id,
                'endpoint' => substr($subscription->endpoint, 0, 50) . '...',
            ]);
        }
        // Se troppi fallimenti, disattiva la subscription
        elseif ($subscription->failure_count >= 5) {
            $subscription->update(['is_active' => false]);
            Log::warning('Deactivated push subscription due to failures', [
                'subscription_id' => $subscription->id,
                'failure_count' => $subscription->failure_count,
            ]);
        }
    }

    /**
     * Invia notifica di benvenuto
     */
    protected function sendWelcomeNotification(PushSubscription $subscription): void
    {
        $notification = [
            'title' => 'Benvenuto in NexioSolution!',
            'body' => 'Le notifiche push sono state attivate con successo.',
            'icon' => '/images/welcome-icon.png',
            'tag' => 'welcome',
            'data' => [
                'type' => 'welcome',
                'url' => '/dashboard',
            ],
        ];

        $this->sendNotification($subscription, $notification);
    }

    /**
     * Valida i dati della subscription
     */
    protected function validateSubscription(array $data): void
    {
        $required = ['endpoint', 'keys'];
        $requiredKeys = ['p256dh', 'auth'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        foreach ($requiredKeys as $key) {
            if (!isset($data['keys'][$key])) {
                throw new \InvalidArgumentException("Missing required key: {$key}");
            }
        }

        // Valida formato endpoint
        if (!filter_var($data['endpoint'], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid endpoint URL");
        }
    }

    /**
     * Aggiorna il file .env
     */
    protected function updateEnvFile(array $data): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            return;
        }

        $envContent = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $pattern = "/^{$key}=.*/m";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $envContent);
    }

    /**
     * Test invio notifica
     */
    public function testNotification(User $user): array
    {
        $notification = [
            'title' => 'Test Notifica',
            'body' => 'Questa è una notifica di test da NexioSolution',
            'icon' => '/images/test-icon.png',
            'tag' => 'test',
            'data' => [
                'type' => 'test',
                'timestamp' => now()->toIso8601String(),
            ],
        ];

        return $this->sendToUser($user, $notification);
    }

    /**
     * Pulisce subscription inattive o non valide
     */
    public function cleanupSubscriptions(): int
    {
        // Rimuovi subscription non utilizzate da più di 90 giorni
        $deleted = PushSubscription::where('last_used_at', '<', now()->subDays(90))->delete();

        // Rimuovi subscription con troppi fallimenti
        $deleted += PushSubscription::where('failure_count', '>=', 10)->delete();

        Log::info('Cleaned up push subscriptions', ['deleted' => $deleted]);

        return $deleted;
    }

    /**
     * Statistiche sulle push notifications
     */
    public function getStatistics(): array
    {
        return [
            'total_subscriptions' => PushSubscription::count(),
            'active_subscriptions' => PushSubscription::active()->count(),
            'subscriptions_last_24h' => PushSubscription::where('created_at', '>=', now()->subDay())->count(),
            'subscriptions_by_browser' => $this->getSubscriptionsByBrowser(),
            'subscriptions_by_os' => $this->getSubscriptionsByOS(),
        ];
    }

    /**
     * Ottiene subscriptions raggruppate per browser
     */
    protected function getSubscriptionsByBrowser(): array
    {
        $subscriptions = PushSubscription::all();
        $browsers = [];

        foreach ($subscriptions as $subscription) {
            $browser = $this->detectBrowser($subscription->user_agent);
            $browsers[$browser] = ($browsers[$browser] ?? 0) + 1;
        }

        return $browsers;
    }

    /**
     * Ottiene subscriptions raggruppate per OS
     */
    protected function getSubscriptionsByOS(): array
    {
        $subscriptions = PushSubscription::all();
        $systems = [];

        foreach ($subscriptions as $subscription) {
            $os = $this->detectOS($subscription->user_agent);
            $systems[$os] = ($systems[$os] ?? 0) + 1;
        }

        return $systems;
    }

    /**
     * Rileva il browser dal user agent
     */
    protected function detectBrowser(string $userAgent): string
    {
        if (stripos($userAgent, 'Chrome') !== false) return 'Chrome';
        if (stripos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (stripos($userAgent, 'Safari') !== false) return 'Safari';
        if (stripos($userAgent, 'Edge') !== false) return 'Edge';
        if (stripos($userAgent, 'Opera') !== false) return 'Opera';

        return 'Other';
    }

    /**
     * Rileva l'OS dal user agent
     */
    protected function detectOS(string $userAgent): string
    {
        if (stripos($userAgent, 'Windows') !== false) return 'Windows';
        if (stripos($userAgent, 'Mac OS') !== false) return 'macOS';
        if (stripos($userAgent, 'Linux') !== false) return 'Linux';
        if (stripos($userAgent, 'Android') !== false) return 'Android';
        if (stripos($userAgent, 'iOS') !== false) return 'iOS';

        return 'Other';
    }
}