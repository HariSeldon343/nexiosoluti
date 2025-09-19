<?php
declare(strict_types=1);

/**
 * Notification Service
 * Gestisce email, webhook, e notifiche push
 */

class NotificationService {
    private array $config;
    private array $queue = [];
    private string $queueFile;
    private array $webhooks = [];
    private array $emailTemplates = [];

    public function __construct() {
        $this->queueFile = APP_ROOT . '/temp/notification_queue.json';
        $this->config = [
            'email' => [
                'enabled' => true,
                'from_email' => 'noreply@nexiosolution.local',
                'from_name' => 'Nexio File Manager',
                'smtp' => [
                    'host' => 'localhost',
                    'port' => 25,
                    'auth' => false,
                    'username' => '',
                    'password' => '',
                    'encryption' => '' // '', 'tls', 'ssl'
                ]
            ],
            'webhooks' => [
                'enabled' => true,
                'timeout' => 30,
                'retry_attempts' => 3,
                'retry_delay' => 60
            ],
            'push' => [
                'enabled' => false,
                'vapid_public_key' => '',
                'vapid_private_key' => '',
                'vapid_subject' => ''
            ]
        ];

        $this->loadQueue();
        $this->loadWebhooks();
        $this->initializeTemplates();
    }

    /**
     * Invia notifica email
     */
    public function sendEmail(array $to, string $subject, string $body, array $options = []): bool {
        if (!$this->config['email']['enabled']) {
            return false;
        }

        $email = [
            'id' => generate_uuid(),
            'type' => 'email',
            'to' => is_array($to) ? $to : [$to],
            'subject' => $subject,
            'body' => $body,
            'html' => $options['html'] ?? true,
            'attachments' => $options['attachments'] ?? [],
            'headers' => $options['headers'] ?? [],
            'priority' => $options['priority'] ?? 'normal',
            'created_at' => time(),
            'attempts' => 0,
            'status' => 'pending'
        ];

        // Add to queue
        $this->addToQueue($email);

        // Process immediately if high priority
        if ($options['priority'] === 'high' || $options['immediate'] ?? false) {
            return $this->processEmail($email);
        }

        return true;
    }

    /**
     * Invia notifica webhook
     */
    public function sendWebhook(string $event, array $data, string $webhookId = null): bool {
        if (!$this->config['webhooks']['enabled']) {
            return false;
        }

        $webhooks = $webhookId
            ? [$this->getWebhook($webhookId)]
            : $this->getWebhooksForEvent($event);

        $success = true;
        foreach ($webhooks as $webhook) {
            if (!$webhook || !$webhook['active']) {
                continue;
            }

            $notification = [
                'id' => generate_uuid(),
                'type' => 'webhook',
                'webhook_id' => $webhook['id'],
                'url' => $webhook['url'],
                'event' => $event,
                'data' => $data,
                'headers' => $this->buildWebhookHeaders($webhook, $data),
                'created_at' => time(),
                'attempts' => 0,
                'status' => 'pending'
            ];

            $this->addToQueue($notification);

            // Process immediately
            if (!$this->processWebhook($notification)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Invia notifica push
     */
    public function sendPush(int $userId, string $title, string $body, array $options = []): bool {
        if (!$this->config['push']['enabled']) {
            return false;
        }

        // Get user push subscriptions
        $subscriptions = $this->getUserPushSubscriptions($userId);

        if (empty($subscriptions)) {
            return false;
        }

        $notification = [
            'id' => generate_uuid(),
            'type' => 'push',
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'icon' => $options['icon'] ?? '/icon.png',
            'badge' => $options['badge'] ?? '/badge.png',
            'url' => $options['url'] ?? '/',
            'data' => $options['data'] ?? [],
            'subscriptions' => $subscriptions,
            'created_at' => time(),
            'attempts' => 0,
            'status' => 'pending'
        ];

        $this->addToQueue($notification);

        // Process immediately
        return $this->processPush($notification);
    }

    /**
     * Invia notifica broadcast a tutti gli utenti di un tenant
     */
    public function broadcast(int $tenantId, string $type, string $message, array $data = []): bool {
        $users = Database::select(
            "SELECT id, email FROM users WHERE tenant_id = :tenant_id AND status = 'active'",
            ['tenant_id' => $tenantId]
        );

        $success = true;
        foreach ($users as $user) {
            switch ($type) {
                case 'email':
                    if (!$this->sendEmail($user['email'], $message, $this->renderTemplate('broadcast', [
                        'message' => $message,
                        'data' => $data
                    ]))) {
                        $success = false;
                    }
                    break;

                case 'push':
                    if (!$this->sendPush($user['id'], 'System Notification', $message, $data)) {
                        $success = false;
                    }
                    break;

                case 'all':
                    $this->sendEmail($user['email'], $message, $this->renderTemplate('broadcast', [
                        'message' => $message,
                        'data' => $data
                    ]));
                    $this->sendPush($user['id'], 'System Notification', $message, $data);
                    break;
            }
        }

        return $success;
    }

    /**
     * Process email notification
     */
    private function processEmail(array &$email): bool {
        $email['attempts']++;

        try {
            $headers = $this->buildEmailHeaders($email);
            $boundary = md5(uniqid(time()));

            // Build email content
            $message = '';

            // Headers
            foreach ($headers as $key => $value) {
                $message .= $key . ': ' . $value . "\r\n";
            }

            // Multipart content
            if (!empty($email['attachments'])) {
                $message .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";
                $message .= "--$boundary\r\n";
            }

            // Body
            if ($email['html']) {
                $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            } else {
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            }
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $email['body'] . "\r\n";

            // Attachments
            foreach ($email['attachments'] as $attachment) {
                $message .= "--$boundary\r\n";
                $message .= "Content-Type: " . $attachment['type'] . "; name=\"" . $attachment['name'] . "\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n";
                $message .= "Content-Disposition: attachment; filename=\"" . $attachment['name'] . "\"\r\n\r\n";
                $message .= chunk_split(base64_encode($attachment['content'])) . "\r\n";
            }

            if (!empty($email['attachments'])) {
                $message .= "--$boundary--\r\n";
            }

            // Send via SMTP or mail()
            if ($this->config['email']['smtp']['host']) {
                $success = $this->sendSmtp($email['to'], $email['subject'], $message);
            } else {
                $success = mail(
                    implode(', ', $email['to']),
                    $email['subject'],
                    $email['body'],
                    implode("\r\n", array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers))
                );
            }

            if ($success) {
                $email['status'] = 'sent';
                $email['sent_at'] = time();
                $this->logNotification('email', 'sent', $email);
            } else {
                $email['status'] = 'failed';
                $email['error'] = 'Failed to send email';
                $this->logNotification('email', 'failed', $email);
            }

            return $success;

        } catch (Exception $e) {
            $email['status'] = 'failed';
            $email['error'] = $e->getMessage();
            $this->logNotification('email', 'error', $email);
            return false;
        }
    }

    /**
     * Process webhook notification
     */
    private function processWebhook(array &$webhook): bool {
        $webhook['attempts']++;

        try {
            $ch = curl_init($webhook['url']);

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($webhook['data']),
                CURLOPT_HTTPHEADER => $webhook['headers'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $this->config['webhooks']['timeout'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new Exception("cURL Error: $error");
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                $webhook['status'] = 'sent';
                $webhook['sent_at'] = time();
                $webhook['response'] = [
                    'code' => $httpCode,
                    'body' => $response
                ];
                $this->logNotification('webhook', 'sent', $webhook);
                return true;
            } else {
                throw new Exception("HTTP $httpCode: $response");
            }

        } catch (Exception $e) {
            $webhook['status'] = 'failed';
            $webhook['error'] = $e->getMessage();

            // Retry logic
            if ($webhook['attempts'] < $this->config['webhooks']['retry_attempts']) {
                $webhook['status'] = 'retry';
                $webhook['next_retry'] = time() + ($this->config['webhooks']['retry_delay'] * $webhook['attempts']);
                $this->addToQueue($webhook);
            }

            $this->logNotification('webhook', 'failed', $webhook);
            return false;
        }
    }

    /**
     * Process push notification
     */
    private function processPush(array &$push): bool {
        $push['attempts']++;

        try {
            $payload = json_encode([
                'title' => $push['title'],
                'body' => $push['body'],
                'icon' => $push['icon'],
                'badge' => $push['badge'],
                'data' => array_merge($push['data'], [
                    'url' => $push['url']
                ])
            ]);

            $success = true;
            foreach ($push['subscriptions'] as $subscription) {
                if (!$this->sendPushToEndpoint($subscription, $payload)) {
                    $success = false;
                }
            }

            if ($success) {
                $push['status'] = 'sent';
                $push['sent_at'] = time();
                $this->logNotification('push', 'sent', $push);
            } else {
                $push['status'] = 'partial';
                $this->logNotification('push', 'partial', $push);
            }

            return $success;

        } catch (Exception $e) {
            $push['status'] = 'failed';
            $push['error'] = $e->getMessage();
            $this->logNotification('push', 'error', $push);
            return false;
        }
    }

    /**
     * Send push to endpoint (stub)
     */
    private function sendPushToEndpoint(array $subscription, string $payload): bool {
        // Implementation requires web-push library
        // This is a stub for the integration
        return true;
    }

    /**
     * Send SMTP email
     */
    private function sendSmtp(array $to, string $subject, string $message): bool {
        $smtp = $this->config['email']['smtp'];

        try {
            $socket = fsockopen(
                $smtp['encryption'] === 'ssl' ? 'ssl://' . $smtp['host'] : $smtp['host'],
                $smtp['port'],
                $errno,
                $errstr,
                30
            );

            if (!$socket) {
                throw new Exception("Failed to connect: $errstr ($errno)");
            }

            // Read greeting
            $this->smtpRead($socket);

            // EHLO
            $this->smtpWrite($socket, "EHLO " . $_SERVER['SERVER_NAME']);
            $this->smtpRead($socket);

            // STARTTLS if needed
            if ($smtp['encryption'] === 'tls') {
                $this->smtpWrite($socket, "STARTTLS");
                $this->smtpRead($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }

            // AUTH if needed
            if ($smtp['auth']) {
                $this->smtpWrite($socket, "AUTH LOGIN");
                $this->smtpRead($socket);
                $this->smtpWrite($socket, base64_encode($smtp['username']));
                $this->smtpRead($socket);
                $this->smtpWrite($socket, base64_encode($smtp['password']));
                $this->smtpRead($socket);
            }

            // MAIL FROM
            $this->smtpWrite($socket, "MAIL FROM: <{$this->config['email']['from_email']}>");
            $this->smtpRead($socket);

            // RCPT TO
            foreach ($to as $recipient) {
                $this->smtpWrite($socket, "RCPT TO: <$recipient>");
                $this->smtpRead($socket);
            }

            // DATA
            $this->smtpWrite($socket, "DATA");
            $this->smtpRead($socket);

            // Send message
            $this->smtpWrite($socket, $message . "\r\n.");
            $this->smtpRead($socket);

            // QUIT
            $this->smtpWrite($socket, "QUIT");
            $this->smtpRead($socket);

            fclose($socket);
            return true;

        } catch (Exception $e) {
            if (isset($socket) && $socket) {
                fclose($socket);
            }
            throw $e;
        }
    }

    private function smtpWrite($socket, string $data): void {
        fwrite($socket, $data . "\r\n");
    }

    private function smtpRead($socket): string {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }

        $code = substr($response, 0, 3);
        if ($code >= 400) {
            throw new Exception("SMTP Error: $response");
        }

        return $response;
    }

    /**
     * Build email headers
     */
    private function buildEmailHeaders(array $email): array {
        $headers = [
            'From' => $this->config['email']['from_name'] . ' <' . $this->config['email']['from_email'] . '>',
            'Reply-To' => $this->config['email']['from_email'],
            'X-Mailer' => 'Nexio Notification Service',
            'MIME-Version' => '1.0',
            'Date' => date('r'),
            'Message-ID' => '<' . $email['id'] . '@' . $_SERVER['SERVER_NAME'] . '>'
        ];

        // Priority
        if ($email['priority'] === 'high') {
            $headers['X-Priority'] = '1';
            $headers['Importance'] = 'High';
        } elseif ($email['priority'] === 'low') {
            $headers['X-Priority'] = '5';
            $headers['Importance'] = 'Low';
        }

        // Custom headers
        foreach ($email['headers'] as $key => $value) {
            $headers[$key] = $value;
        }

        return $headers;
    }

    /**
     * Build webhook headers
     */
    private function buildWebhookHeaders(array $webhook, array $data): array {
        $headers = [
            'Content-Type: application/json',
            'User-Agent: Nexio Webhook/1.0',
            'X-Webhook-ID: ' . $webhook['id'],
            'X-Webhook-Event: ' . ($data['event'] ?? 'unknown'),
            'X-Webhook-Timestamp: ' . time()
        ];

        // Add signature if secret is set
        if (!empty($webhook['secret'])) {
            $signature = hash_hmac('sha256', json_encode($data), $webhook['secret']);
            $headers[] = 'X-Webhook-Signature: sha256=' . $signature;
        }

        // Add custom headers
        if (!empty($webhook['headers'])) {
            foreach ($webhook['headers'] as $key => $value) {
                $headers[] = "$key: $value";
            }
        }

        return $headers;
    }

    /**
     * Webhook management
     */
    public function createWebhook(array $data): string {
        $webhook = [
            'id' => generate_uuid(),
            'tenant_id' => $data['tenant_id'],
            'name' => $data['name'],
            'url' => $data['url'],
            'events' => $data['events'] ?? ['*'],
            'secret' => $data['secret'] ?? '',
            'headers' => $data['headers'] ?? [],
            'active' => true,
            'created_at' => time()
        ];

        $this->webhooks[] = $webhook;
        $this->saveWebhooks();

        return $webhook['id'];
    }

    public function updateWebhook(string $id, array $data): bool {
        foreach ($this->webhooks as &$webhook) {
            if ($webhook['id'] === $id) {
                $webhook = array_merge($webhook, $data);
                $webhook['updated_at'] = time();
                $this->saveWebhooks();
                return true;
            }
        }
        return false;
    }

    public function deleteWebhook(string $id): bool {
        $this->webhooks = array_filter($this->webhooks, fn($w) => $w['id'] !== $id);
        $this->saveWebhooks();
        return true;
    }

    public function getWebhook(string $id): ?array {
        foreach ($this->webhooks as $webhook) {
            if ($webhook['id'] === $id) {
                return $webhook;
            }
        }
        return null;
    }

    public function getWebhooksForEvent(string $event): array {
        return array_filter($this->webhooks, function($webhook) use ($event) {
            return $webhook['active'] &&
                   (in_array('*', $webhook['events']) || in_array($event, $webhook['events']));
        });
    }

    public function listWebhooks(int $tenantId = null): array {
        if ($tenantId === null) {
            return $this->webhooks;
        }

        return array_filter($this->webhooks, fn($w) => $w['tenant_id'] === $tenantId);
    }

    /**
     * Template management
     */
    private function initializeTemplates(): void {
        $this->emailTemplates = [
            'welcome' => [
                'subject' => 'Welcome to Nexio File Manager',
                'body' => '<h1>Welcome!</h1><p>Your account has been created successfully.</p>'
            ],
            'password_reset' => [
                'subject' => 'Password Reset Request',
                'body' => '<h1>Password Reset</h1><p>Click the link below to reset your password:</p><p><a href="{reset_link}">Reset Password</a></p>'
            ],
            'file_shared' => [
                'subject' => 'File Shared With You',
                'body' => '<h1>File Shared</h1><p>{sender_name} has shared a file with you: {file_name}</p>'
            ],
            'storage_warning' => [
                'subject' => 'Storage Limit Warning',
                'body' => '<h1>Storage Warning</h1><p>You have used {percentage}% of your storage quota.</p>'
            ],
            'broadcast' => [
                'subject' => 'System Notification',
                'body' => '<h1>System Notification</h1><p>{message}</p>'
            ]
        ];
    }

    public function renderTemplate(string $name, array $variables = []): string {
        if (!isset($this->emailTemplates[$name])) {
            return '';
        }

        $template = $this->emailTemplates[$name]['body'];

        foreach ($variables as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }

        return $template;
    }

    public function getTemplateSubject(string $name): string {
        return $this->emailTemplates[$name]['subject'] ?? '';
    }

    /**
     * Queue management
     */
    private function addToQueue(array $notification): void {
        $this->queue[] = $notification;
        $this->saveQueue();
    }

    public function processQueue(): int {
        $processed = 0;
        $now = time();

        foreach ($this->queue as $key => &$notification) {
            // Skip if not ready for retry
            if (isset($notification['next_retry']) && $notification['next_retry'] > $now) {
                continue;
            }

            // Skip if already processed
            if (in_array($notification['status'], ['sent', 'failed'])) {
                continue;
            }

            $success = false;
            switch ($notification['type']) {
                case 'email':
                    $success = $this->processEmail($notification);
                    break;
                case 'webhook':
                    $success = $this->processWebhook($notification);
                    break;
                case 'push':
                    $success = $this->processPush($notification);
                    break;
            }

            if ($success || $notification['status'] === 'failed') {
                unset($this->queue[$key]);
                $processed++;
            }
        }

        $this->queue = array_values($this->queue);
        $this->saveQueue();

        return $processed;
    }

    private function loadQueue(): void {
        if (file_exists($this->queueFile)) {
            $content = file_get_contents($this->queueFile);
            $this->queue = json_decode($content, true) ?: [];
        }
    }

    private function saveQueue(): void {
        $dir = dirname($this->queueFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            $this->queueFile,
            json_encode($this->queue),
            LOCK_EX
        );
    }

    private function loadWebhooks(): void {
        $file = APP_ROOT . '/temp/webhooks.json';
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $this->webhooks = json_decode($content, true) ?: [];
        }
    }

    private function saveWebhooks(): void {
        $file = APP_ROOT . '/temp/webhooks.json';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            $file,
            json_encode($this->webhooks),
            LOCK_EX
        );
    }

    /**
     * Push subscription management
     */
    private function getUserPushSubscriptions(int $userId): array {
        // Stub - would retrieve from database
        return [];
    }

    public function savePushSubscription(int $userId, array $subscription): bool {
        // Stub - would save to database
        return true;
    }

    /**
     * Logging
     */
    private function logNotification(string $type, string $status, array $data): void {
        $logFile = LOG_PATH . '/notifications_' . date('Y-m-d') . '.log';
        $logEntry = date('Y-m-d H:i:s') . " [$type] [$status] " . json_encode($data) . PHP_EOL;

        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0777, true);
        }

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Statistics
     */
    public function getStats(): array {
        return [
            'queue_size' => count($this->queue),
            'webhooks_count' => count($this->webhooks),
            'pending_emails' => count(array_filter($this->queue, fn($n) => $n['type'] === 'email' && $n['status'] === 'pending')),
            'pending_webhooks' => count(array_filter($this->queue, fn($n) => $n['type'] === 'webhook' && $n['status'] === 'pending')),
            'pending_push' => count(array_filter($this->queue, fn($n) => $n['type'] === 'push' && $n['status'] === 'pending'))
        ];
    }
}