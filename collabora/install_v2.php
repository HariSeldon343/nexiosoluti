<?php
/**
 * Installazione V2 - Script di Setup Sistema di Autenticazione Avanzato
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config_v2.php';

class Installer {
    private $db = null;
    private $errors = [];
    private $success = [];
    private $step = 1;

    public function __construct() {
        if (isset($_POST['step'])) {
            $this->step = (int)$_POST['step'];
        }
    }

    /**
     * Esegui installazione
     */
    public function run() {
        // Se richiesta AJAX
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            $this->processAjaxRequest();
            exit;
        }

        // Altrimenti mostra interfaccia
        $this->displayInterface();
    }

    /**
     * Processa richieste AJAX
     */
    private function processAjaxRequest() {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'check_requirements':
                echo json_encode($this->checkRequirements());
                break;

            case 'create_database':
                echo json_encode($this->createDatabase());
                break;

            case 'create_tables':
                echo json_encode($this->createTables());
                break;

            case 'create_admin':
                echo json_encode($this->createAdminUser());
                break;

            case 'create_sample_data':
                echo json_encode($this->createSampleData());
                break;

            case 'finalize':
                echo json_encode($this->finalizeInstallation());
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Azione non valida']);
        }
    }

    /**
     * Verifica requisiti
     */
    private function checkRequirements() {
        $requirements = [];
        $all_ok = true;

        // PHP Version
        $php_version = phpversion();
        $php_ok = version_compare($php_version, '7.4.0', '>=');
        $requirements[] = [
            'name' => 'PHP Version',
            'required' => '≥ 7.4.0',
            'current' => $php_version,
            'status' => $php_ok
        ];
        if (!$php_ok) $all_ok = false;

        // Estensioni PHP
        $extensions = [
            'mysqli' => 'MySQLi',
            'pdo' => 'PDO',
            'pdo_mysql' => 'PDO MySQL',
            'json' => 'JSON',
            'mbstring' => 'Multibyte String',
            'openssl' => 'OpenSSL',
            'session' => 'Session',
            'fileinfo' => 'File Info'
        ];

        foreach ($extensions as $ext => $name) {
            $loaded = extension_loaded($ext);
            $requirements[] = [
                'name' => $name,
                'required' => 'Installato',
                'current' => $loaded ? 'Installato' : 'Mancante',
                'status' => $loaded
            ];
            if (!$loaded) $all_ok = false;
        }

        // Directory scrivibili
        $directories = [
            'uploads' => UPLOAD_PATH,
            'temp' => TEMP_PATH,
            'logs' => LOG_PATH,
            'backups' => BACKUP_PATH
        ];

        foreach ($directories as $name => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            $writable = is_writable($path);
            $requirements[] = [
                'name' => "Directory $name",
                'required' => 'Scrivibile',
                'current' => $writable ? 'Scrivibile' : 'Non scrivibile',
                'status' => $writable
            ];
            if (!$writable) $all_ok = false;
        }

        return [
            'success' => $all_ok,
            'requirements' => $requirements,
            'message' => $all_ok ? 'Tutti i requisiti soddisfatti' : 'Alcuni requisiti non sono soddisfatti'
        ];
    }

    /**
     * Crea database
     */
    private function createDatabase() {
        try {
            // Connetti senza selezionare database
            $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Crea database se non esiste
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`
                        DEFAULT CHARACTER SET utf8mb4
                        COLLATE utf8mb4_unicode_ci");

            return [
                'success' => true,
                'message' => 'Database creato con successo'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Errore creazione database: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crea tabelle
     */
    private function createTables() {
        try {
            $this->db = getDbConnection();

            // SQL per creare le tabelle
            $sql = "
            -- Tabella Tenants
            CREATE TABLE IF NOT EXISTS `tenants` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `code` varchar(50) NOT NULL UNIQUE,
                `name` varchar(100) NOT NULL,
                `description` text,
                `logo_url` varchar(255),
                `settings` JSON,
                `status` enum('active','inactive','suspended') DEFAULT 'active',
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_code` (`code`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- Tabella Users
            CREATE TABLE IF NOT EXISTS `users` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `email` varchar(100) NOT NULL UNIQUE,
                `password` varchar(255) NOT NULL,
                `name` varchar(100) NOT NULL,
                `phone` varchar(20),
                `avatar_url` varchar(255),
                `role` enum('admin','special_user','standard_user','guest') DEFAULT 'standard_user',
                `status` enum('active','inactive','suspended','pending') DEFAULT 'pending',
                `email_verified_at` timestamp NULL,
                `two_factor_secret` varchar(255),
                `two_factor_enabled` boolean DEFAULT false,
                `last_login_at` timestamp NULL,
                `last_login_ip` varchar(45),
                `settings` JSON,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_email` (`email`),
                KEY `idx_role` (`role`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- Tabella User-Tenant Associations
            CREATE TABLE IF NOT EXISTS `user_tenants` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `tenant_id` int(11) NOT NULL,
                `is_primary` boolean DEFAULT false,
                `permissions` JSON,
                `joined_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_user_tenant` (`user_id`, `tenant_id`),
                KEY `idx_user` (`user_id`),
                KEY `idx_tenant` (`tenant_id`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- Tabella Sessions
            CREATE TABLE IF NOT EXISTS `sessions` (
                `id` varchar(128) NOT NULL,
                `user_id` int(11) NOT NULL,
                `tenant_id` int(11),
                `token` varchar(255) NOT NULL UNIQUE,
                `ip_address` varchar(45),
                `user_agent` varchar(255),
                `payload` text,
                `last_activity` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `expires_at` timestamp NULL,
                PRIMARY KEY (`id`),
                KEY `idx_user` (`user_id`),
                KEY `idx_token` (`token`),
                KEY `idx_expires` (`expires_at`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- Tabella Login Attempts
            CREATE TABLE IF NOT EXISTS `login_attempts` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `email` varchar(100),
                `ip_address` varchar(45) NOT NULL,
                `user_agent` varchar(255),
                `attempted_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `success` boolean DEFAULT false,
                PRIMARY KEY (`id`),
                KEY `idx_email` (`email`),
                KEY `idx_ip` (`ip_address`),
                KEY `idx_attempted` (`attempted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- Tabella Password Resets
            CREATE TABLE IF NOT EXISTS `password_resets` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `email` varchar(100) NOT NULL,
                `token` varchar(255) NOT NULL,
                `expires_at` timestamp NOT NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_email` (`email`),
                KEY `idx_token` (`token`),
                KEY `idx_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- Tabella Audit Logs
            CREATE TABLE IF NOT EXISTS `audit_logs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11),
                `tenant_id` int(11),
                `action` varchar(100) NOT NULL,
                `entity_type` varchar(50),
                `entity_id` int(11),
                `old_values` JSON,
                `new_values` JSON,
                `details` text,
                `ip_address` varchar(45),
                `user_agent` varchar(255),
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_user` (`user_id`),
                KEY `idx_tenant` (`tenant_id`),
                KEY `idx_action` (`action`),
                KEY `idx_entity` (`entity_type`, `entity_id`),
                KEY `idx_created` (`created_at`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- Tabella Notifications
            CREATE TABLE IF NOT EXISTS `notifications` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `tenant_id` int(11),
                `type` varchar(50) NOT NULL,
                `title` varchar(255) NOT NULL,
                `message` text,
                `data` JSON,
                `read_at` timestamp NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_user` (`user_id`),
                KEY `idx_tenant` (`tenant_id`),
                KEY `idx_type` (`type`),
                KEY `idx_read` (`read_at`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- Tabella Tasks
            CREATE TABLE IF NOT EXISTS `tasks` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `tenant_id` int(11) NOT NULL,
                `user_id` int(11) NOT NULL,
                `assigned_to` int(11),
                `title` varchar(255) NOT NULL,
                `description` text,
                `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
                `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
                `due_date` date,
                `completed_at` timestamp NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_tenant` (`tenant_id`),
                KEY `idx_user` (`user_id`),
                KEY `idx_assigned` (`assigned_to`),
                KEY `idx_status` (`status`),
                KEY `idx_priority` (`priority`),
                KEY `idx_due` (`due_date`),
                FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- Tabella Files
            CREATE TABLE IF NOT EXISTS `files` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `tenant_id` int(11) NOT NULL,
                `user_id` int(11) NOT NULL,
                `folder_id` int(11),
                `filename` varchar(255) NOT NULL,
                `original_name` varchar(255) NOT NULL,
                `mime_type` varchar(100),
                `size` bigint,
                `path` varchar(500) NOT NULL,
                `is_public` boolean DEFAULT false,
                `downloads` int DEFAULT 0,
                `metadata` JSON,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_tenant` (`tenant_id`),
                KEY `idx_user` (`user_id`),
                KEY `idx_folder` (`folder_id`),
                KEY `idx_filename` (`filename`),
                FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- Tabella Folders
            CREATE TABLE IF NOT EXISTS `folders` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `tenant_id` int(11) NOT NULL,
                `user_id` int(11) NOT NULL,
                `parent_id` int(11),
                `name` varchar(255) NOT NULL,
                `path` varchar(500),
                `is_public` boolean DEFAULT false,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_tenant` (`tenant_id`),
                KEY `idx_user` (`user_id`),
                KEY `idx_parent` (`parent_id`),
                FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`parent_id`) REFERENCES `folders`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- Tabella Calendar Events
            CREATE TABLE IF NOT EXISTS `calendar_events` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `tenant_id` int(11) NOT NULL,
                `user_id` int(11) NOT NULL,
                `title` varchar(255) NOT NULL,
                `description` text,
                `location` varchar(255),
                `start_date` datetime NOT NULL,
                `end_date` datetime NOT NULL,
                `all_day` boolean DEFAULT false,
                `recurring` boolean DEFAULT false,
                `recurring_pattern` JSON,
                `color` varchar(7) DEFAULT '#3788d8',
                `reminder_minutes` int,
                `attendees` JSON,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_tenant` (`tenant_id`),
                KEY `idx_user` (`user_id`),
                KEY `idx_dates` (`start_date`, `end_date`),
                FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- Tabella Chat Messages
            CREATE TABLE IF NOT EXISTS `chat_messages` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `tenant_id` int(11) NOT NULL,
                `sender_id` int(11) NOT NULL,
                `recipient_id` int(11),
                `room_id` int(11),
                `message` text NOT NULL,
                `type` enum('text','file','image','system') DEFAULT 'text',
                `file_id` int(11),
                `is_edited` boolean DEFAULT false,
                `edited_at` timestamp NULL,
                `is_deleted` boolean DEFAULT false,
                `deleted_at` timestamp NULL,
                `read_at` timestamp NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_tenant` (`tenant_id`),
                KEY `idx_sender` (`sender_id`),
                KEY `idx_recipient` (`recipient_id`),
                KEY `idx_room` (`room_id`),
                KEY `idx_created` (`created_at`),
                FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- Tabella Chat Rooms
            CREATE TABLE IF NOT EXISTS `chat_rooms` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `tenant_id` int(11) NOT NULL,
                `name` varchar(255) NOT NULL,
                `description` text,
                `type` enum('public','private','direct') DEFAULT 'public',
                `created_by` int(11) NOT NULL,
                `avatar_url` varchar(255),
                `last_message_at` timestamp NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_tenant` (`tenant_id`),
                KEY `idx_type` (`type`),
                KEY `idx_created_by` (`created_by`),
                FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            -- Tabella Chat Room Members
            CREATE TABLE IF NOT EXISTS `chat_room_members` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `room_id` int(11) NOT NULL,
                `user_id` int(11) NOT NULL,
                `role` enum('admin','moderator','member') DEFAULT 'member',
                `joined_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `last_read_at` timestamp NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_room_member` (`room_id`, `user_id`),
                KEY `idx_room` (`room_id`),
                KEY `idx_user` (`user_id`),
                FOREIGN KEY (`room_id`) REFERENCES `chat_rooms`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";

            // Esegui ogni statement separatamente
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->db->exec($statement);
                }
            }

            return [
                'success' => true,
                'message' => 'Tabelle create con successo'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Errore creazione tabelle: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crea utente amministratore
     */
    private function createAdminUser() {
        try {
            $this->db = getDbConnection();

            // Verifica se admin esiste già
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([DEFAULT_ADMIN_EMAIL]);

            if ($stmt->rowCount() > 0) {
                // Aggiorna password
                $stmt = $this->db->prepare("
                    UPDATE users
                    SET password = ?, role = 'admin', status = 'active'
                    WHERE email = ?
                ");
                $stmt->execute([
                    password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT),
                    DEFAULT_ADMIN_EMAIL
                ]);
                $message = 'Utente admin aggiornato';
            } else {
                // Crea nuovo admin
                $stmt = $this->db->prepare("
                    INSERT INTO users (email, password, name, phone, role, status, email_verified_at)
                    VALUES (?, ?, ?, ?, 'admin', 'active', NOW())
                ");
                $stmt->execute([
                    DEFAULT_ADMIN_EMAIL,
                    password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT),
                    DEFAULT_ADMIN_NAME,
                    DEFAULT_ADMIN_PHONE
                ]);
                $message = 'Utente admin creato';
            }

            // Crea tenant default
            $stmt = $this->db->prepare("
                INSERT INTO tenants (code, name, description, status)
                VALUES (?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE name = VALUES(name)
            ");
            $stmt->execute([
                'FORTIBYTE',
                'Fortibyte Solutions',
                'Tenant principale amministrativo'
            ]);

            // Associa admin al tenant
            $admin_id = $this->db->lastInsertId() ?: $this->db->query("SELECT id FROM users WHERE email = '" . DEFAULT_ADMIN_EMAIL . "'")->fetch()['id'];
            $tenant_id = $this->db->lastInsertId() ?: $this->db->query("SELECT id FROM tenants WHERE code = 'FORTIBYTE'")->fetch()['id'];

            if ($admin_id && $tenant_id) {
                $stmt = $this->db->prepare("
                    INSERT INTO user_tenants (user_id, tenant_id, is_primary)
                    VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE is_primary = 1
                ");
                $stmt->execute([$admin_id, $tenant_id]);
            }

            return [
                'success' => true,
                'message' => $message . ' con successo',
                'credentials' => [
                    'email' => DEFAULT_ADMIN_EMAIL,
                    'password' => DEFAULT_ADMIN_PASSWORD
                ]
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Errore creazione admin: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crea dati di esempio
     */
    private function createSampleData() {
        try {
            $this->db = getDbConnection();

            // Crea tenants di esempio
            $tenants = [
                ['code' => 'DEMO01', 'name' => 'Demo Azienda 1', 'description' => 'Prima azienda demo per test'],
                ['code' => 'DEMO02', 'name' => 'Demo Azienda 2', 'description' => 'Seconda azienda demo per test'],
                ['code' => 'DEMO03', 'name' => 'Demo Azienda 3', 'description' => 'Terza azienda demo per test']
            ];

            $tenant_ids = [];
            foreach ($tenants as $tenant) {
                $stmt = $this->db->prepare("
                    INSERT INTO tenants (code, name, description, status)
                    VALUES (?, ?, ?, 'active')
                    ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
                ");
                $stmt->execute([$tenant['code'], $tenant['name'], $tenant['description']]);
                $tenant_ids[] = $this->db->lastInsertId();
            }

            // Crea utenti di esempio
            $users = [
                [
                    'email' => 'special@demo.com',
                    'name' => 'Utente Speciale Demo',
                    'role' => 'special_user',
                    'tenants' => [$tenant_ids[0], $tenant_ids[1]] // Multi-tenant
                ],
                [
                    'email' => 'user1@demo.com',
                    'name' => 'Utente Standard 1',
                    'role' => 'standard_user',
                    'tenants' => [$tenant_ids[0]] // Single tenant
                ],
                [
                    'email' => 'user2@demo.com',
                    'name' => 'Utente Standard 2',
                    'role' => 'standard_user',
                    'tenants' => [$tenant_ids[1]] // Single tenant
                ],
                [
                    'email' => 'user3@demo.com',
                    'name' => 'Utente Standard 3',
                    'role' => 'standard_user',
                    'tenants' => [$tenant_ids[2]] // Single tenant
                ]
            ];

            foreach ($users as $user) {
                // Crea utente
                $stmt = $this->db->prepare("
                    INSERT INTO users (email, password, name, role, status, email_verified_at)
                    VALUES (?, ?, ?, ?, 'active', NOW())
                    ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
                ");
                $stmt->execute([
                    $user['email'],
                    password_hash('Demo@2024', PASSWORD_DEFAULT),
                    $user['name'],
                    $user['role']
                ]);
                $user_id = $this->db->lastInsertId();

                // Associa ai tenant
                foreach ($user['tenants'] as $index => $tenant_id) {
                    $stmt = $this->db->prepare("
                        INSERT INTO user_tenants (user_id, tenant_id, is_primary)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary)
                    ");
                    $is_primary = ($index === 0) ? 1 : 0;
                    $stmt->execute([$user_id, $tenant_id, $is_primary]);
                }
            }

            // Crea alcuni task di esempio
            foreach ($tenant_ids as $tenant_id) {
                for ($i = 1; $i <= 3; $i++) {
                    $stmt = $this->db->prepare("
                        INSERT INTO tasks (tenant_id, user_id, title, description, priority, status, due_date)
                        SELECT ?, u.id, ?, ?, ?, ?, ?
                        FROM users u
                        JOIN user_tenants ut ON u.id = ut.user_id
                        WHERE ut.tenant_id = ?
                        LIMIT 1
                    ");

                    $priorities = ['low', 'medium', 'high'];
                    $statuses = ['pending', 'in_progress', 'completed'];

                    $stmt->execute([
                        $tenant_id,
                        "Task Demo $i",
                        "Descrizione del task demo numero $i",
                        $priorities[array_rand($priorities)],
                        $statuses[array_rand($statuses)],
                        date('Y-m-d', strtotime("+$i days")),
                        $tenant_id
                    ]);
                }
            }

            // Crea alcuni eventi calendario
            foreach ($tenant_ids as $tenant_id) {
                for ($i = 1; $i <= 2; $i++) {
                    $stmt = $this->db->prepare("
                        INSERT INTO calendar_events (tenant_id, user_id, title, description, start_date, end_date, color)
                        SELECT ?, u.id, ?, ?, ?, ?, ?
                        FROM users u
                        JOIN user_tenants ut ON u.id = ut.user_id
                        WHERE ut.tenant_id = ?
                        LIMIT 1
                    ");

                    $colors = ['#3788d8', '#4caf50', '#ff9800', '#f44336'];

                    $stmt->execute([
                        $tenant_id,
                        "Evento Demo $i",
                        "Descrizione evento demo $i",
                        date('Y-m-d H:i:s', strtotime("+$i days 10:00")),
                        date('Y-m-d H:i:s', strtotime("+$i days 11:00")),
                        $colors[array_rand($colors)],
                        $tenant_id
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => 'Dati di esempio creati con successo',
                'details' => [
                    'tenants' => count($tenants),
                    'users' => count($users),
                    'tasks' => count($tenant_ids) * 3,
                    'events' => count($tenant_ids) * 2
                ]
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Errore creazione dati esempio: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Finalizza installazione
     */
    private function finalizeInstallation() {
        try {
            // Crea file di lock per indicare installazione completata
            $lock_file = BASE_PATH . '/install.lock';
            file_put_contents($lock_file, date('Y-m-d H:i:s'));

            // Genera chiave API casuale
            $api_key = bin2hex(random_bytes(32));
            $config_update = "
// API Key generata automaticamente
define('API_MASTER_KEY', '$api_key');
";
            file_put_contents(BASE_PATH . '/config_v2_keys.php', "<?php\n" . $config_update);

            // Crea .htaccess per sicurezza
            $htaccess = "
# Sicurezza Nexio Solution V2
Options -Indexes
Options -MultiViews

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /Nexiosolution/collabora/

    # Blocca accesso a file sensibili
    RewriteRule ^(config|install|test).*\.php$ - [F,L]
    RewriteRule ^\. - [F,L]

    # Reindirizza tutto a index_v2.php
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^api/v2/(.*)$ api/v2/index.php?route=$1 [QSA,L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . index_v2.php [L]
</IfModule>

# Sicurezza headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options \"nosniff\"
    Header set X-Frame-Options \"SAMEORIGIN\"
    Header set X-XSS-Protection \"1; mode=block\"
</IfModule>
";
            file_put_contents(BASE_PATH . '/.htaccess', $htaccess);

            return [
                'success' => true,
                'message' => 'Installazione completata con successo!',
                'next_steps' => [
                    'Login come admin: ' . DEFAULT_ADMIN_EMAIL,
                    'Password: ' . DEFAULT_ADMIN_PASSWORD,
                    'URL: ' . BASE_URL . '/index_v2.php'
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Errore finalizzazione: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Mostra interfaccia installazione
     */
    private function displayInterface() {
        ?>
        <!DOCTYPE html>
        <html lang="it">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Installazione - Nexio Solution V2</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }

                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }

                .container {
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    max-width: 800px;
                    width: 100%;
                    overflow: hidden;
                }

                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 30px;
                    text-align: center;
                }

                .header h1 {
                    font-size: 28px;
                    margin-bottom: 10px;
                }

                .header p {
                    opacity: 0.9;
                    font-size: 16px;
                }

                .content {
                    padding: 40px;
                }

                .steps {
                    display: flex;
                    margin-bottom: 40px;
                    position: relative;
                }

                .step {
                    flex: 1;
                    text-align: center;
                    position: relative;
                }

                .step-number {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    background: #e0e0e0;
                    color: #999;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                    margin-bottom: 10px;
                    position: relative;
                    z-index: 2;
                }

                .step.active .step-number {
                    background: #667eea;
                    color: white;
                }

                .step.completed .step-number {
                    background: #4caf50;
                    color: white;
                }

                .step-title {
                    font-size: 14px;
                    color: #666;
                }

                .step.active .step-title {
                    color: #667eea;
                    font-weight: 600;
                }

                .steps::before {
                    content: '';
                    position: absolute;
                    top: 20px;
                    left: 50px;
                    right: 50px;
                    height: 2px;
                    background: #e0e0e0;
                    z-index: 1;
                }

                .progress-bar {
                    position: absolute;
                    top: 20px;
                    left: 50px;
                    height: 2px;
                    background: #667eea;
                    z-index: 1;
                    transition: width 0.3s ease;
                }

                .install-section {
                    display: none;
                }

                .install-section.active {
                    display: block;
                    animation: fadeIn 0.3s ease;
                }

                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                .requirement {
                    display: flex;
                    justify-content: space-between;
                    padding: 15px;
                    margin: 10px 0;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border-left: 4px solid #e0e0e0;
                }

                .requirement.success {
                    border-left-color: #4caf50;
                }

                .requirement.error {
                    border-left-color: #f44336;
                }

                .requirement-name {
                    font-weight: 500;
                }

                .requirement-status {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                .icon {
                    width: 20px;
                    height: 20px;
                }

                .btn {
                    padding: 12px 30px;
                    border: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    margin: 10px 5px;
                }

                .btn-primary {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                }

                .btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
                }

                .btn-secondary {
                    background: #e0e0e0;
                    color: #666;
                }

                .btn:disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }

                .alert {
                    padding: 15px 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                }

                .alert-success {
                    background: #e8f5e9;
                    color: #2e7d32;
                    border-left: 4px solid #4caf50;
                }

                .alert-error {
                    background: #ffebee;
                    color: #c62828;
                    border-left: 4px solid #f44336;
                }

                .alert-info {
                    background: #e3f2fd;
                    color: #1565c0;
                    border-left: 4px solid #2196f3;
                }

                .loading {
                    display: inline-block;
                    width: 20px;
                    height: 20px;
                    border: 3px solid rgba(255,255,255,.3);
                    border-radius: 50%;
                    border-top-color: white;
                    animation: spin 1s ease-in-out infinite;
                }

                @keyframes spin {
                    to { transform: rotate(360deg); }
                }

                .credentials-box {
                    background: #f8f9fa;
                    border: 2px dashed #667eea;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 20px 0;
                }

                .credentials-box h3 {
                    color: #667eea;
                    margin-bottom: 15px;
                }

                .credential-item {
                    display: flex;
                    justify-content: space-between;
                    padding: 10px 0;
                    border-bottom: 1px solid #e0e0e0;
                }

                .credential-item:last-child {
                    border-bottom: none;
                }

                .credential-label {
                    font-weight: 500;
                    color: #666;
                }

                .credential-value {
                    font-family: monospace;
                    background: white;
                    padding: 5px 10px;
                    border-radius: 4px;
                    color: #333;
                }

                .footer {
                    text-align: center;
                    padding: 20px;
                    background: #f8f9fa;
                    color: #666;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🚀 Installazione Nexio Solution V2</h1>
                    <p>Sistema di Autenticazione e Gestione Multi-Tenant Avanzato</p>
                </div>

                <div class="content">
                    <!-- Progress Steps -->
                    <div class="steps">
                        <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                        <div class="step active" data-step="1">
                            <div class="step-number">1</div>
                            <div class="step-title">Benvenuto</div>
                        </div>
                        <div class="step" data-step="2">
                            <div class="step-number">2</div>
                            <div class="step-title">Requisiti</div>
                        </div>
                        <div class="step" data-step="3">
                            <div class="step-number">3</div>
                            <div class="step-title">Database</div>
                        </div>
                        <div class="step" data-step="4">
                            <div class="step-number">4</div>
                            <div class="step-title">Installazione</div>
                        </div>
                        <div class="step" data-step="5">
                            <div class="step-number">5</div>
                            <div class="step-title">Completato</div>
                        </div>
                    </div>

                    <!-- Step 1: Welcome -->
                    <div class="install-section active" id="step1">
                        <h2>Benvenuto nell'installazione guidata</h2>
                        <p style="margin: 20px 0; color: #666; line-height: 1.6;">
                            Questa procedura configurerà il sistema Nexio Solution V2 con:
                        </p>
                        <ul style="margin: 20px 0; color: #666; line-height: 1.8; list-style: none;">
                            <li>✅ Sistema di autenticazione avanzato senza codice tenant</li>
                            <li>✅ Gestione multi-tenant per utenti speciali</li>
                            <li>✅ Ruoli gerarchici (Admin, Special, Standard)</li>
                            <li>✅ Dashboard personalizzata per ruolo</li>
                            <li>✅ API RESTful v2 con autenticazione JWT</li>
                            <li>✅ Sistema di audit e log completo</li>
                        </ul>

                        <div class="alert alert-info">
                            <strong>Nota:</strong> Assicurati che XAMPP sia in esecuzione con Apache e MySQL attivi.
                        </div>

                        <div style="text-align: center; margin-top: 30px;">
                            <button class="btn btn-primary" onclick="nextStep()">Inizia Installazione →</button>
                        </div>
                    </div>

                    <!-- Step 2: Requirements -->
                    <div class="install-section" id="step2">
                        <h2>Verifica Requisiti Sistema</h2>
                        <div id="requirementsList">
                            <p style="text-align: center; color: #666; margin: 40px 0;">
                                <span class="loading"></span><br><br>
                                Verifica requisiti in corso...
                            </p>
                        </div>
                        <div style="text-align: center; margin-top: 30px;">
                            <button class="btn btn-secondary" onclick="prevStep()">← Indietro</button>
                            <button class="btn btn-primary" id="btnNext2" disabled onclick="nextStep()">Continua →</button>
                        </div>
                    </div>

                    <!-- Step 3: Database -->
                    <div class="install-section" id="step3">
                        <h2>Configurazione Database</h2>

                        <div class="credentials-box">
                            <h3>Impostazioni Database Correnti</h3>
                            <div class="credential-item">
                                <span class="credential-label">Host:</span>
                                <span class="credential-value"><?php echo DB_HOST; ?></span>
                            </div>
                            <div class="credential-item">
                                <span class="credential-label">Database:</span>
                                <span class="credential-value"><?php echo DB_NAME; ?></span>
                            </div>
                            <div class="credential-item">
                                <span class="credential-label">Utente:</span>
                                <span class="credential-value"><?php echo DB_USER; ?></span>
                            </div>
                            <div class="credential-item">
                                <span class="credential-label">Charset:</span>
                                <span class="credential-value"><?php echo DB_CHARSET; ?></span>
                            </div>
                        </div>

                        <div id="databaseStatus"></div>

                        <div style="text-align: center; margin-top: 30px;">
                            <button class="btn btn-secondary" onclick="prevStep()">← Indietro</button>
                            <button class="btn btn-primary" onclick="setupDatabase()">Crea Database</button>
                            <button class="btn btn-primary" id="btnNext3" style="display:none" onclick="nextStep()">Continua →</button>
                        </div>
                    </div>

                    <!-- Step 4: Installation -->
                    <div class="install-section" id="step4">
                        <h2>Installazione in Corso</h2>

                        <div id="installationProgress">
                            <div class="requirement" id="prog-tables">
                                <span class="requirement-name">Creazione tabelle database...</span>
                                <span class="requirement-status">
                                    <span class="loading"></span>
                                </span>
                            </div>
                            <div class="requirement" id="prog-admin">
                                <span class="requirement-name">Creazione utente amministratore...</span>
                                <span class="requirement-status">⏳</span>
                            </div>
                            <div class="requirement" id="prog-data">
                                <span class="requirement-name">Inserimento dati di esempio...</span>
                                <span class="requirement-status">⏳</span>
                            </div>
                            <div class="requirement" id="prog-finalize">
                                <span class="requirement-name">Finalizzazione configurazione...</span>
                                <span class="requirement-status">⏳</span>
                            </div>
                        </div>

                        <div id="installationStatus"></div>
                    </div>

                    <!-- Step 5: Complete -->
                    <div class="install-section" id="step5">
                        <h2 style="text-align: center; color: #4caf50;">✅ Installazione Completata!</h2>

                        <div class="credentials-box">
                            <h3>🔐 Credenziali Amministratore</h3>
                            <div class="credential-item">
                                <span class="credential-label">Email:</span>
                                <span class="credential-value"><?php echo DEFAULT_ADMIN_EMAIL; ?></span>
                            </div>
                            <div class="credential-item">
                                <span class="credential-label">Password:</span>
                                <span class="credential-value"><?php echo DEFAULT_ADMIN_PASSWORD; ?></span>
                            </div>
                        </div>

                        <div class="alert alert-success">
                            Il sistema è stato installato con successo! Puoi ora accedere con le credenziali di amministratore.
                        </div>

                        <div class="credentials-box">
                            <h3>👥 Utenti Demo (Password: Demo@2024)</h3>
                            <div class="credential-item">
                                <span class="credential-label">Special User:</span>
                                <span class="credential-value">special@demo.com</span>
                            </div>
                            <div class="credential-item">
                                <span class="credential-label">Standard User 1:</span>
                                <span class="credential-value">user1@demo.com</span>
                            </div>
                            <div class="credential-item">
                                <span class="credential-label">Standard User 2:</span>
                                <span class="credential-value">user2@demo.com</span>
                            </div>
                        </div>

                        <div style="text-align: center; margin-top: 30px;">
                            <a href="index_v2.php" class="btn btn-primary" style="text-decoration: none; display: inline-block;">
                                Vai al Login →
                            </a>
                            <a href="test_v2.php" class="btn btn-secondary" style="text-decoration: none; display: inline-block;">
                                Esegui Test Sistema
                            </a>
                        </div>

                        <div class="alert alert-info" style="margin-top: 30px;">
                            <strong>Prossimi passi:</strong>
                            <ul style="margin: 10px 0; padding-left: 20px;">
                                <li>Cambia la password dell'amministratore al primo accesso</li>
                                <li>Configura le impostazioni email SMTP in config_v2.php</li>
                                <li>Elimina install_v2.php per sicurezza</li>
                                <li>Esegui start_v2.bat per avviare automaticamente il sistema</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="footer">
                    Nexio Solution V2 &copy; 2024 - Powered by Fortibyte Solutions
                </div>
            </div>

            <script>
                let currentStep = 1;
                const totalSteps = 5;

                function updateProgress() {
                    const progressPercent = ((currentStep - 1) / (totalSteps - 1)) * 100;
                    document.getElementById('progressBar').style.width = progressPercent + '%';

                    // Update step indicators
                    document.querySelectorAll('.step').forEach((step, index) => {
                        const stepNum = index + 1;
                        step.classList.remove('active', 'completed');
                        if (stepNum < currentStep) {
                            step.classList.add('completed');
                        } else if (stepNum === currentStep) {
                            step.classList.add('active');
                        }
                    });

                    // Show/hide sections
                    document.querySelectorAll('.install-section').forEach(section => {
                        section.classList.remove('active');
                    });
                    document.getElementById('step' + currentStep).classList.add('active');
                }

                function nextStep() {
                    if (currentStep < totalSteps) {
                        currentStep++;
                        updateProgress();

                        // Trigger actions for specific steps
                        if (currentStep === 2) {
                            checkRequirements();
                        } else if (currentStep === 4) {
                            startInstallation();
                        }
                    }
                }

                function prevStep() {
                    if (currentStep > 1) {
                        currentStep--;
                        updateProgress();
                    }
                }

                async function checkRequirements() {
                    try {
                        const response = await fetch('install_v2.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'ajax=1&action=check_requirements'
                        });
                        const result = await response.json();

                        let html = '';
                        result.requirements.forEach(req => {
                            const statusClass = req.status ? 'success' : 'error';
                            const icon = req.status ? '✓' : '✗';
                            html += `
                                <div class="requirement ${statusClass}">
                                    <span class="requirement-name">${req.name}</span>
                                    <span class="requirement-status">
                                        <span>${req.current}</span>
                                        <span style="color: ${req.status ? '#4caf50' : '#f44336'}">${icon}</span>
                                    </span>
                                </div>
                            `;
                        });

                        document.getElementById('requirementsList').innerHTML = html;
                        document.getElementById('btnNext2').disabled = !result.success;

                        if (!result.success) {
                            html += '<div class="alert alert-error">Correggi i requisiti mancanti prima di continuare.</div>';
                            document.getElementById('requirementsList').innerHTML = html;
                        }

                    } catch (error) {
                        document.getElementById('requirementsList').innerHTML =
                            '<div class="alert alert-error">Errore verifica requisiti: ' + error.message + '</div>';
                    }
                }

                async function setupDatabase() {
                    try {
                        // Create database
                        let response = await fetch('install_v2.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'ajax=1&action=create_database'
                        });
                        let result = await response.json();

                        if (result.success) {
                            document.getElementById('databaseStatus').innerHTML =
                                '<div class="alert alert-success">' + result.message + '</div>';

                            // Create tables
                            response = await fetch('install_v2.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                body: 'ajax=1&action=create_tables'
                            });
                            result = await response.json();

                            if (result.success) {
                                document.getElementById('databaseStatus').innerHTML +=
                                    '<div class="alert alert-success">' + result.message + '</div>';
                                document.getElementById('btnNext3').style.display = 'inline-block';
                            } else {
                                throw new Error(result.message);
                            }
                        } else {
                            throw new Error(result.message);
                        }

                    } catch (error) {
                        document.getElementById('databaseStatus').innerHTML =
                            '<div class="alert alert-error">' + error.message + '</div>';
                    }
                }

                async function startInstallation() {
                    const steps = [
                        {id: 'prog-tables', action: 'create_tables', name: 'Creazione tabelle'},
                        {id: 'prog-admin', action: 'create_admin', name: 'Creazione admin'},
                        {id: 'prog-data', action: 'create_sample_data', name: 'Dati esempio'},
                        {id: 'prog-finalize', action: 'finalize', name: 'Finalizzazione'}
                    ];

                    for (const step of steps) {
                        const element = document.getElementById(step.id);
                        element.querySelector('.requirement-status').innerHTML = '<span class="loading"></span>';

                        try {
                            const response = await fetch('install_v2.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                body: 'ajax=1&action=' + step.action
                            });
                            const result = await response.json();

                            if (result.success) {
                                element.classList.add('success');
                                element.querySelector('.requirement-status').innerHTML = '✓';
                            } else {
                                throw new Error(result.message);
                            }

                        } catch (error) {
                            element.classList.add('error');
                            element.querySelector('.requirement-status').innerHTML = '✗';
                            document.getElementById('installationStatus').innerHTML =
                                '<div class="alert alert-error">Errore ' + step.name + ': ' + error.message + '</div>';
                            return;
                        }
                    }

                    // All done, go to final step
                    setTimeout(() => {
                        currentStep = 5;
                        updateProgress();
                    }, 1500);
                }

                // Initialize
                updateProgress();
            </script>
        </body>
        </html>
        <?php
    }
}

// Check if already installed
if (file_exists('install.lock') && !isset($_GET['force'])) {
    die('Sistema già installato. Elimina install.lock per reinstallare o aggiungi ?force=1');
}

// Run installer
$installer = new Installer();
$installer->run();