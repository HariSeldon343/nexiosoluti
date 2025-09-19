<?php
declare(strict_types=1);

/**
 * Database connection e query helpers
 * Gestione connessione PDO con supporto multi-tenant
 */

class Database {
    private static ?PDO $instance = null;
    private static array $queryLog = [];
    private static bool $enableLogging = false;

    /**
     * Ottiene istanza singleton del database
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=%s',
                    DB_HOST,
                    DB_NAME,
                    DB_CHARSET
                );

                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                    PDO::ATTR_PERSISTENT => false
                ];

                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);

                // Imposta timezone
                self::$instance->exec("SET time_zone = '+01:00'");

            } catch (PDOException $e) {
                self::logError('Database connection failed: ' . $e->getMessage());
                throw new Exception('Database connection failed');
            }
        }
        return self::$instance;
    }

    /**
     * Esegue una query con prepared statement
     */
    public static function query(string $sql, array $params = []): PDOStatement {
        $start = microtime(true);

        try {
            $db = self::getInstance();
            $stmt = $db->prepare($sql);

            // Bind parametri con tipo corretto
            foreach ($params as $key => $value) {
                $type = PDO::PARAM_STR;
                if (is_int($value)) {
                    $type = PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $type = PDO::PARAM_BOOL;
                } elseif (is_null($value)) {
                    $type = PDO::PARAM_NULL;
                }

                $stmt->bindValue(
                    is_int($key) ? $key + 1 : ':' . $key,
                    $value,
                    $type
                );
            }

            $stmt->execute();

            if (self::$enableLogging) {
                self::$queryLog[] = [
                    'sql' => $sql,
                    'params' => $params,
                    'time' => microtime(true) - $start,
                    'rows' => $stmt->rowCount()
                ];
            }

            return $stmt;

        } catch (PDOException $e) {
            self::logError('Query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            throw new Exception('Database query failed: ' . $e->getMessage());
        }
    }

    /**
     * Esegue SELECT e ritorna array di risultati
     */
    public static function select(string $sql, array $params = []): array {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Esegue SELECT e ritorna prima riga
     */
    public static function selectOne(string $sql, array $params = []): ?array {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Esegue SELECT e ritorna valore singolo
     */
    public static function selectValue(string $sql, array $params = []): mixed {
        $stmt = self::query($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Esegue INSERT e ritorna ID inserito
     */
    public static function insert(string $table, array $data): int {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        self::query($sql, $data);
        return (int) self::getInstance()->lastInsertId();
    }

    /**
     * Esegue UPDATE con condizioni WHERE
     */
    public static function update(string $table, array $data, array $where): int {
        $setClauses = [];
        $params = [];

        foreach ($data as $column => $value) {
            $setClauses[] = $column . ' = :set_' . $column;
            $params['set_' . $column] = $value;
        }

        $whereClauses = [];
        foreach ($where as $column => $value) {
            if (is_array($value)) {
                $placeholders = [];
                foreach ($value as $i => $v) {
                    $placeholder = 'where_' . $column . '_' . $i;
                    $placeholders[] = ':' . $placeholder;
                    $params[$placeholder] = $v;
                }
                $whereClauses[] = $column . ' IN (' . implode(', ', $placeholders) . ')';
            } else {
                $whereClauses[] = $column . ' = :where_' . $column;
                $params['where_' . $column] = $value;
            }
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $setClauses),
            implode(' AND ', $whereClauses)
        );

        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Esegue DELETE con condizioni WHERE
     */
    public static function delete(string $table, array $where): int {
        $whereClauses = [];
        $params = [];

        foreach ($where as $column => $value) {
            if (is_array($value)) {
                $placeholders = [];
                foreach ($value as $i => $v) {
                    $placeholder = 'where_' . $column . '_' . $i;
                    $placeholders[] = ':' . $placeholder;
                    $params[$placeholder] = $v;
                }
                $whereClauses[] = $column . ' IN (' . implode(', ', $placeholders) . ')';
            } else {
                $whereClauses[] = $column . ' = :' . $column;
                $params[$column] = $value;
            }
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode(' AND ', $whereClauses)
        );

        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Inizia una transazione
     */
    public static function beginTransaction(): bool {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Conferma una transazione
     */
    public static function commit(): bool {
        return self::getInstance()->commit();
    }

    /**
     * Annulla una transazione
     */
    public static function rollback(): bool {
        return self::getInstance()->rollBack();
    }

    /**
     * Esegue codice in transazione
     */
    public static function transaction(callable $callback): mixed {
        self::beginTransaction();
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (Exception $e) {
            self::rollback();
            throw $e;
        }
    }

    /**
     * Conta record con condizioni WHERE
     */
    public static function count(string $table, array $where = []): int {
        $sql = 'SELECT COUNT(*) FROM ' . $table;
        $params = [];

        if (!empty($where)) {
            $whereClauses = [];
            foreach ($where as $column => $value) {
                $whereClauses[] = $column . ' = :' . $column;
                $params[$column] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        return (int) self::selectValue($sql, $params);
    }

    /**
     * Verifica esistenza record
     */
    public static function exists(string $table, array $where): bool {
        return self::count($table, $where) > 0;
    }

    /**
     * Escape stringa per LIKE
     */
    public static function escapeLike(string $string): string {
        return str_replace(['%', '_'], ['\\%', '\\_'], $string);
    }

    /**
     * Log errori database
     */
    private static function logError(string $message): void {
        $logFile = LOG_PATH . '/database_' . date('Y-m-d') . '.log';
        $logMessage = date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL;

        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0777, true);
        }

        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Ottiene query log
     */
    public static function getQueryLog(): array {
        return self::$queryLog;
    }

    /**
     * Abilita/disabilita query logging
     */
    public static function setLogging(bool $enable): void {
        self::$enableLogging = $enable;
    }

    /**
     * Pulisce vecchi log
     */
    public static function cleanOldLogs(int $days = 30): int {
        $sql = 'DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)';
        $stmt = self::query($sql, ['days' => $days]);
        return $stmt->rowCount();
    }

    /**
     * Ottiene statistiche database
     */
    public static function getStats(): array {
        $stats = [];

        // Dimensione database
        $sql = "SELECT
                    table_schema AS db_name,
                    SUM(data_length + index_length) AS db_size
                FROM information_schema.tables
                WHERE table_schema = :dbname
                GROUP BY table_schema";

        $result = self::selectOne($sql, ['dbname' => DB_NAME]);
        $stats['database_size'] = format_bytes((int)($result['db_size'] ?? 0));

        // Conteggi tabelle principali
        $tables = ['users', 'tenants', 'files', 'folders', 'activity_logs'];
        foreach ($tables as $table) {
            if (self::tableExists($table)) {
                $stats[$table . '_count'] = self::count($table);
            }
        }

        return $stats;
    }

    /**
     * Verifica se tabella esiste
     */
    public static function tableExists(string $table): bool {
        $sql = "SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = :dbname AND table_name = :table";

        $count = self::selectValue($sql, [
            'dbname' => DB_NAME,
            'table' => $table
        ]);

        return $count > 0;
    }

    /**
     * Crea tabelle necessarie se non esistono
     */
    public static function createTables(): void {
        $db = self::getInstance();

        // Disabilita temporaneamente i foreign key checks
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");

        try {
            // Tabella tenants
            $db->exec("CREATE TABLE IF NOT EXISTS tenants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(255) NOT NULL,
                domain VARCHAR(255),
                status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                storage_limit BIGINT DEFAULT 10737418240,
                storage_used BIGINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_code (code),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Tabella users
            $db->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                username VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(255),
                role ENUM('admin', 'manager', 'user') DEFAULT 'user',
                status ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
                last_login TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_tenant_email (tenant_id, email),
                UNIQUE KEY unique_tenant_username (tenant_id, username),
                INDEX idx_tenant (tenant_id),
                INDEX idx_status (status),
                CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Tabella folders
            $db->exec("CREATE TABLE IF NOT EXISTS folders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                parent_id INT DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                path TEXT,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_parent (parent_id),
                INDEX idx_created_by (created_by),
                CONSTRAINT fk_folders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT fk_folders_parent FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE,
                CONSTRAINT fk_folders_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Tabella files
            $db->exec("CREATE TABLE IF NOT EXISTS files (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                folder_id INT DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(100),
                extension VARCHAR(20),
                size BIGINT NOT NULL,
                hash VARCHAR(64) NOT NULL,
                path TEXT NOT NULL,
                is_deleted BOOLEAN DEFAULT FALSE,
                deleted_at TIMESTAMP NULL,
                uploaded_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_folder (folder_id),
                INDEX idx_hash (hash),
                INDEX idx_deleted (is_deleted, deleted_at),
                INDEX idx_uploaded_by (uploaded_by),
                CONSTRAINT fk_files_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT fk_files_folder FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL,
                CONSTRAINT fk_files_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Tabella activity_logs - Modificata per gestire user_id che potrebbe essere 0 per azioni di sistema
            $db->exec("CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT DEFAULT NULL,
                action VARCHAR(50) NOT NULL,
                entity_type VARCHAR(50),
                entity_id INT,
                details JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_user (user_id),
                INDEX idx_action (action),
                INDEX idx_created (created_at),
                CONSTRAINT fk_logs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Tabella sessions
            $db->exec("CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(128) PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                payload TEXT,
                last_activity INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_user (user_id),
                INDEX idx_last_activity (last_activity),
                CONSTRAINT fk_sessions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        } finally {
            // Riabilita i foreign key checks
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        }
    }
}

/**
 * Helper function per ottenere la connessione al database
 * Utilizzata dai moduli di autenticazione e gestione
 *
 * @return PDO Connessione database
 */
if (!function_exists('getDbConnection')) {
    function getDbConnection(): PDO {
        // Carica configurazione se non già definita
        if (!defined('DB_HOST')) {
            $configFile = dirname(__DIR__) . '/config_v2.php';
            if (file_exists($configFile)) {
                require_once $configFile;
            } else {
                $configFile = dirname(__DIR__) . '/config.php';
                if (file_exists($configFile)) {
                    require_once $configFile;
                } else {
                    // Configurazione di fallback per sviluppo
                    define('DB_HOST', 'localhost');
                    define('DB_NAME', 'collabora_files');
                    define('DB_USER', 'root');
                    define('DB_PASS', '');
                    define('DB_CHARSET', 'utf8mb4');
                    define('LOG_PATH', dirname(__DIR__) . '/logs');
                }
            }
        }

        return Database::getInstance();
    }
}