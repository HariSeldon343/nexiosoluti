<?php
/**
 * Nexiosolution Collabora - Automated Installation Script
 * @version 1.0.0
 * @author Nexiosolution Team
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Installation configuration
define('MIN_PHP_VERSION', '7.4.0');
define('MIN_MYSQL_VERSION', '5.7.0');
define('REQUIRED_EXTENSIONS', ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl', 'gd', 'zip', 'fileinfo']);
define('REQUIRED_DIRS', ['uploads', 'logs', 'database', 'assets/cache']);
define('CONFIG_FILE', __DIR__ . '/config.php');

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$messages = [];

// Helper functions
function checkRequirement($condition, $message) {
    return [
        'status' => $condition ? 'success' : 'error',
        'message' => $message,
        'passed' => $condition
    ];
}

function generateRandomKey($length = 32) {
    return bin2hex(random_bytes($length));
}

function createDirectory($path) {
    if (!file_exists($path)) {
        return mkdir($path, 0777, true);
    }
    return chmod($path, 0777);
}

function testDatabaseConnection($host, $port, $username, $password, $database) {
    try {
        $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if database exists, create if not
        $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$database'");
        if (!$stmt->fetch()) {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        return ['success' => true, 'message' => 'Database connection successful'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
    }
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 3) {
        // Database configuration
        $db_config = [
            'host' => $_POST['db_host'] ?? 'localhost',
            'port' => $_POST['db_port'] ?? '3306',
            'database' => $_POST['db_name'] ?? 'nexio_collabora',
            'username' => $_POST['db_user'] ?? 'root',
            'password' => $_POST['db_pass'] ?? ''
        ];

        $test = testDatabaseConnection(
            $db_config['host'],
            $db_config['port'],
            $db_config['username'],
            $db_config['password'],
            $db_config['database']
        );

        if ($test['success']) {
            $_SESSION['db_config'] = $db_config;
            header('Location: install.php?step=4');
            exit;
        } else {
            $errors[] = $test['message'];
        }
    } elseif ($step == 4) {
        // Application configuration
        $_SESSION['app_config'] = [
            'site_name' => $_POST['site_name'] ?? 'Nexiosolution Collabora',
            'site_url' => $_POST['site_url'] ?? 'http://localhost/Nexiosolution/collabora',
            'admin_email' => $_POST['admin_email'] ?? 'admin@nexiosolution.local',
            'admin_username' => $_POST['admin_username'] ?? 'admin',
            'admin_password' => $_POST['admin_password'] ?? 'admin123',
            'demo_data' => isset($_POST['demo_data'])
        ];
        header('Location: install.php?step=5');
        exit;
    } elseif ($step == 5) {
        // Final installation
        $db = $_SESSION['db_config'];
        $app = $_SESSION['app_config'];

        // Create configuration file
        $config_content = "<?php
/**
 * Nexiosolution Collabora Configuration
 * Generated: " . date('Y-m-d H:i:s') . "
 */

// Database Configuration
define('DB_HOST', '{$db['host']}');
define('DB_PORT', '{$db['port']}');
define('DB_NAME', '{$db['database']}');
define('DB_USER', '{$db['username']}');
define('DB_PASS', '{$db['password']}');

// Application Settings
define('SITE_NAME', '{$app['site_name']}');
define('SITE_URL', '{$app['site_url']}');
define('ADMIN_EMAIL', '{$app['admin_email']}');

// Security Keys
define('SECRET_KEY', '" . generateRandomKey() . "');
define('JWT_SECRET', '" . generateRandomKey() . "');
define('ENCRYPTION_KEY', '" . generateRandomKey(16) . "');

// Directory Paths
define('ROOT_DIR', __DIR__);
define('UPLOAD_DIR', ROOT_DIR . '/uploads');
define('LOG_DIR', ROOT_DIR . '/logs');
define('CACHE_DIR', ROOT_DIR . '/assets/cache');

// Upload Settings
define('MAX_UPLOAD_SIZE', 104857600); // 100MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar']);

// Session Settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'NEXIO_COLLABORA_SESSION');

// Debug Mode
define('DEBUG_MODE', false);
define('LOG_ERRORS', true);

// Email Settings (using PHP mail() for now)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 25);
define('SMTP_AUTH', false);
define('SMTP_USER', '');
define('SMTP_PASS', '');

// API Settings
define('API_RATE_LIMIT', 100); // requests per minute
define('API_KEY_LENGTH', 32);

// WebDAV Settings
define('WEBDAV_ENABLED', true);
define('WEBDAV_PATH', '/webdav');

// Collabora/OnlyOffice Settings
define('OFFICE_ENABLED', false);
define('OFFICE_URL', '');

// System Constants
define('VERSION', '1.0.0');
define('INSTALLED', true);
define('INSTALL_DATE', '" . date('Y-m-d H:i:s') . "');

// Load functions
require_once ROOT_DIR . '/includes/functions.php';
require_once ROOT_DIR . '/includes/database.php';
require_once ROOT_DIR . '/includes/session.php';
?>";

        file_put_contents(CONFIG_FILE, $config_content);

        // Create database tables
        try {
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db['username'], $db['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Users table
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100),
                role ENUM('admin', 'user', 'guest') DEFAULT 'user',
                status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
                api_key VARCHAR(64) UNIQUE,
                storage_quota BIGINT DEFAULT 1073741824,
                storage_used BIGINT DEFAULT 0,
                last_login DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Folders table
            $pdo->exec("CREATE TABLE IF NOT EXISTS folders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                parent_id INT DEFAULT NULL,
                user_id INT NOT NULL,
                path VARCHAR(500),
                color VARCHAR(7) DEFAULT '#5e72e4',
                icon VARCHAR(50) DEFAULT 'folder',
                is_shared BOOLEAN DEFAULT FALSE,
                share_link VARCHAR(100) UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_parent (parent_id),
                INDEX idx_user (user_id),
                INDEX idx_path (path)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Files table
            $pdo->exec("CREATE TABLE IF NOT EXISTS files (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                folder_id INT DEFAULT NULL,
                user_id INT NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size BIGINT NOT NULL,
                mime_type VARCHAR(100),
                extension VARCHAR(10),
                checksum VARCHAR(64),
                version INT DEFAULT 1,
                is_starred BOOLEAN DEFAULT FALSE,
                is_shared BOOLEAN DEFAULT FALSE,
                share_link VARCHAR(100) UNIQUE,
                share_password VARCHAR(255),
                share_expires DATETIME,
                download_count INT DEFAULT 0,
                last_accessed DATETIME,
                metadata JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_folder (folder_id),
                INDEX idx_user (user_id),
                INDEX idx_checksum (checksum),
                INDEX idx_extension (extension)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // File versions table
            $pdo->exec("CREATE TABLE IF NOT EXISTS file_versions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                file_id INT NOT NULL,
                version_number INT NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size BIGINT NOT NULL,
                checksum VARCHAR(64),
                created_by INT NOT NULL,
                comment TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_file_version (file_id, version_number),
                INDEX idx_file (file_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Permissions table
            $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                resource_type ENUM('file', 'folder') NOT NULL,
                resource_id INT NOT NULL,
                user_id INT NOT NULL,
                can_read BOOLEAN DEFAULT TRUE,
                can_write BOOLEAN DEFAULT FALSE,
                can_delete BOOLEAN DEFAULT FALSE,
                can_share BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_permission (resource_type, resource_id, user_id),
                INDEX idx_resource (resource_type, resource_id),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Activity logs table
            $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action VARCHAR(50) NOT NULL,
                resource_type VARCHAR(50),
                resource_id INT,
                resource_name VARCHAR(255),
                ip_address VARCHAR(45),
                user_agent TEXT,
                details JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user (user_id),
                INDEX idx_action (action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Sessions table
            $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(128) PRIMARY KEY,
                user_id INT NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                payload TEXT NOT NULL,
                last_activity INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user (user_id),
                INDEX idx_last_activity (last_activity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Tags table
            $pdo->exec("CREATE TABLE IF NOT EXISTS tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) UNIQUE NOT NULL,
                color VARCHAR(7) DEFAULT '#6c757d',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // File tags table
            $pdo->exec("CREATE TABLE IF NOT EXISTS file_tags (
                file_id INT NOT NULL,
                tag_id INT NOT NULL,
                PRIMARY KEY (file_id, tag_id),
                FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Comments table
            $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                file_id INT NOT NULL,
                user_id INT NOT NULL,
                comment TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_file (file_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Insert admin user
            $admin_password = password_hash($app['admin_password'], PASSWORD_DEFAULT);
            $api_key = generateRandomKey();

            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, api_key) VALUES (?, ?, ?, ?, 'admin', ?)");
            $stmt->execute([
                $app['admin_username'],
                $app['admin_email'],
                $admin_password,
                'System Administrator',
                $api_key
            ]);

            // Insert demo data if requested
            if ($app['demo_data']) {
                // Add demo users
                $demo_users = [
                    ['john.doe', 'john.doe@example.com', 'John Doe', 'user'],
                    ['jane.smith', 'jane.smith@example.com', 'Jane Smith', 'user'],
                    ['guest.user', 'guest@example.com', 'Guest User', 'guest']
                ];

                foreach ($demo_users as $user) {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, api_key) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $user[0],
                        $user[1],
                        password_hash('demo123', PASSWORD_DEFAULT),
                        $user[2],
                        $user[3],
                        generateRandomKey()
                    ]);
                }

                // Add demo folders
                $stmt = $pdo->prepare("INSERT INTO folders (name, user_id, path, color, icon) VALUES (?, 1, ?, ?, ?)");
                $demo_folders = [
                    ['Documents', '/Documents', '#28a745', 'file-text'],
                    ['Images', '/Images', '#ffc107', 'image'],
                    ['Projects', '/Projects', '#17a2b8', 'briefcase'],
                    ['Shared', '/Shared', '#dc3545', 'share-2']
                ];

                foreach ($demo_folders as $folder) {
                    $stmt->execute([$folder[0], $folder[1], $folder[2], $folder[3]]);
                }

                // Add demo tags
                $stmt = $pdo->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
                $demo_tags = [
                    ['Important', '#dc3545'],
                    ['Work', '#007bff'],
                    ['Personal', '#28a745'],
                    ['Archive', '#6c757d']
                ];

                foreach ($demo_tags as $tag) {
                    $stmt->execute($tag);
                }
            }

            $messages[] = 'Database tables created successfully!';
            $messages[] = 'Admin user created: ' . $app['admin_username'];
            $messages[] = 'API Key: ' . $api_key;

            // Clear session
            session_destroy();

            $step = 6; // Installation complete

        } catch (PDOException $e) {
            $errors[] = 'Database setup failed: ' . $e->getMessage();
        }
    }
}

// System requirements check
if ($step == 1) {
    $requirements = [];

    // PHP Version
    $requirements[] = checkRequirement(
        version_compare(PHP_VERSION, MIN_PHP_VERSION, '>='),
        'PHP Version ' . PHP_VERSION . ' (Required: ' . MIN_PHP_VERSION . '+)'
    );

    // PHP Extensions
    foreach (REQUIRED_EXTENSIONS as $ext) {
        $requirements[] = checkRequirement(
            extension_loaded($ext),
            'PHP Extension: ' . $ext
        );
    }

    // MySQL/MariaDB check
    $requirements[] = checkRequirement(
        extension_loaded('pdo_mysql'),
        'MySQL/MariaDB PDO Driver'
    );

    // Directory permissions
    foreach (REQUIRED_DIRS as $dir) {
        $path = __DIR__ . '/' . $dir;
        $writable = is_writable(dirname($path));
        $requirements[] = checkRequirement(
            $writable,
            'Directory writable: ' . $dir
        );
    }

    // Config file writable
    $requirements[] = checkRequirement(
        is_writable(__DIR__),
        'Configuration directory writable'
    );

    $all_passed = !in_array(false, array_column($requirements, 'passed'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexiosolution Collabora - Installation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .installer {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }

        .installer-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .installer-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .installer-header p {
            opacity: 0.9;
        }

        .progress-bar {
            background: rgba(255,255,255,0.2);
            height: 4px;
            margin-top: 20px;
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-fill {
            background: white;
            height: 100%;
            transition: width 0.3s ease;
        }

        .installer-body {
            padding: 40px;
        }

        .step-title {
            font-size: 24px;
            margin-bottom: 30px;
            color: #333;
        }

        .requirement-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .requirement-item:hover {
            transform: translateX(5px);
        }

        .requirement-item.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }

        .requirement-item.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }

        .requirement-icon {
            width: 30px;
            height: 30px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .success .requirement-icon {
            background: #28a745;
            color: white;
        }

        .error .requirement-icon {
            background: #dc3545;
            color: white;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 13px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
        }

        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .complete-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
        }

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .info-box h3 {
            margin-bottom: 10px;
            color: #333;
        }

        .info-box p {
            margin: 5px 0;
            color: #666;
        }

        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="installer">
        <div class="installer-header">
            <h1>Nexiosolution Collabora</h1>
            <p>Installation Wizard v1.0</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo ($step / 6) * 100; ?>%"></div>
            </div>
        </div>

        <div class="installer-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p>⚠️ <?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($messages)): ?>
                <div class="alert alert-success">
                    <?php foreach ($messages as $message): ?>
                        <p>✅ <?php echo htmlspecialchars($message); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($step == 1): ?>
                <h2 class="step-title">Step 1: System Requirements</h2>

                <?php foreach ($requirements as $req): ?>
                    <div class="requirement-item <?php echo $req['status']; ?>">
                        <div class="requirement-icon">
                            <?php echo $req['status'] === 'success' ? '✓' : '✗'; ?>
                        </div>
                        <div class="requirement-text">
                            <?php echo $req['message']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="button-group">
                    <div></div>
                    <?php if ($all_passed): ?>
                        <a href="?step=2" class="btn btn-primary">Continue →</a>
                    <?php else: ?>
                        <button class="btn btn-primary" disabled>Fix Requirements First</button>
                    <?php endif; ?>
                </div>

            <?php elseif ($step == 2): ?>
                <h2 class="step-title">Step 2: License Agreement</h2>

                <div class="info-box">
                    <h3>MIT License</h3>
                    <p>Copyright (c) 2024 Nexiosolution</p>
                    <p>This software is provided "as is", without warranty of any kind.</p>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="accept_license" onchange="document.getElementById('continue_btn').disabled = !this.checked">
                    <label for="accept_license">I accept the license agreement</label>
                </div>

                <div class="button-group">
                    <a href="?step=1" class="btn btn-secondary">← Back</a>
                    <a href="?step=3" id="continue_btn" class="btn btn-primary" onclick="return document.getElementById('accept_license').checked">Continue →</a>
                </div>

            <?php elseif ($step == 3): ?>
                <h2 class="step-title">Step 3: Database Configuration</h2>

                <form method="POST" action="?step=3">
                    <div class="form-group">
                        <label for="db_host">Database Host</label>
                        <input type="text" id="db_host" name="db_host" value="localhost" required>
                        <small>Usually 'localhost' for XAMPP installations</small>
                    </div>

                    <div class="form-group">
                        <label for="db_port">Database Port</label>
                        <input type="text" id="db_port" name="db_port" value="3306" required>
                        <small>Default MySQL port is 3306</small>
                    </div>

                    <div class="form-group">
                        <label for="db_name">Database Name</label>
                        <input type="text" id="db_name" name="db_name" value="nexio_collabora" required>
                        <small>Will be created if it doesn't exist</small>
                    </div>

                    <div class="form-group">
                        <label for="db_user">Database Username</label>
                        <input type="text" id="db_user" name="db_user" value="root" required>
                        <small>For XAMPP, usually 'root'</small>
                    </div>

                    <div class="form-group">
                        <label for="db_pass">Database Password</label>
                        <input type="password" id="db_pass" name="db_pass" value="">
                        <small>Leave empty for XAMPP default</small>
                    </div>

                    <div class="button-group">
                        <a href="?step=2" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn btn-primary">Test Connection →</button>
                    </div>
                </form>

            <?php elseif ($step == 4): ?>
                <h2 class="step-title">Step 4: Application Configuration</h2>

                <form method="POST" action="?step=4">
                    <div class="form-group">
                        <label for="site_name">Site Name</label>
                        <input type="text" id="site_name" name="site_name" value="Nexiosolution Collabora" required>
                    </div>

                    <div class="form-group">
                        <label for="site_url">Site URL</label>
                        <input type="url" id="site_url" name="site_url" value="http://localhost/Nexiosolution/collabora" required>
                        <small>Full URL to your installation</small>
                    </div>

                    <div class="form-group">
                        <label for="admin_email">Administrator Email</label>
                        <input type="email" id="admin_email" name="admin_email" value="admin@nexiosolution.local" required>
                    </div>

                    <div class="form-group">
                        <label for="admin_username">Administrator Username</label>
                        <input type="text" id="admin_username" name="admin_username" value="admin" required>
                    </div>

                    <div class="form-group">
                        <label for="admin_password">Administrator Password</label>
                        <input type="password" id="admin_password" name="admin_password" value="admin123" required>
                        <small>Minimum 6 characters</small>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="demo_data" name="demo_data" checked>
                        <label for="demo_data">Install demo data (recommended for testing)</label>
                    </div>

                    <div class="button-group">
                        <a href="?step=3" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn btn-primary">Install →</button>
                    </div>
                </form>

            <?php elseif ($step == 5): ?>
                <h2 class="step-title">Step 5: Installing...</h2>

                <div class="alert alert-info">
                    <p>⏳ Creating configuration file...</p>
                    <p>⏳ Setting up database tables...</p>
                    <p>⏳ Creating administrator account...</p>
                    <p>⏳ Installing demo data...</p>
                </div>

                <form method="POST" action="?step=5">
                    <button type="submit" class="btn btn-primary">Complete Installation</button>
                </form>

            <?php elseif ($step == 6): ?>
                <div class="complete-icon">✓</div>
                <h2 class="step-title">Installation Complete!</h2>

                <div class="alert alert-success">
                    <p>✅ Nexiosolution Collabora has been successfully installed!</p>
                </div>

                <div class="info-box">
                    <h3>Login Credentials</h3>
                    <p><strong>Username:</strong> <?php echo $_SESSION['app_config']['admin_username'] ?? 'admin'; ?></p>
                    <p><strong>Password:</strong> [as configured]</p>
                </div>

                <div class="info-box">
                    <h3>Important Security Notes</h3>
                    <p>⚠️ Delete or rename install.php after installation</p>
                    <p>⚠️ Change the default admin password immediately</p>
                    <p>⚠️ Review and update security settings in config.php</p>
                </div>

                <div class="button-group">
                    <a href="test.php" class="btn btn-secondary">Run System Test</a>
                    <a href="index.php" class="btn btn-primary">Go to Application →</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>