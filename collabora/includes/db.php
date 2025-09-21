<?php

require_once __DIR__ . '/../config.php';

/**
 * Classe Database - gestisce la connessione PDO come singleton
 */
class Database
{
    private static ?Database $instance = null;
    private \PDO $connection;

    private function __construct()
    {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->connection = new \PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $exception) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Errore di connessione al database',
                'error' => $exception->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
