-- Installazione completa Collabora Platform

CREATE DATABASE IF NOT EXISTS collabora CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE collabora;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Tabella tenant (aziende)
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    logo_path VARCHAR(500) NULL,
    settings JSON DEFAULT '{}',
    storage_used BIGINT DEFAULT 0,
    storage_limit BIGINT DEFAULT 10737418240,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabella utenti
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('admin', 'special', 'user') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    preferences JSON DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email_tenant (tenant_id, email),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_active (tenant_id, is_active)
) ENGINE=InnoDB;

-- Tabella per utenti speciali multi-tenant
CREATE TABLE IF NOT EXISTS user_tenant_access (
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, tenant_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Tabella cartelle
CREATE TABLE IF NOT EXISTS folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    parent_id INT NULL,
    name VARCHAR(255) NOT NULL,
    path VARCHAR(1000) NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE KEY unique_path (tenant_id, path),
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB;

-- Tabella file
CREATE TABLE IF NOT EXISTS files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    folder_id INT NULL,
    uploaded_by INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size BIGINT NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    hash_sha256 VARCHAR(64) NOT NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_tenant_folder (tenant_id, folder_id),
    INDEX idx_hash (hash_sha256),
    INDEX idx_deleted (is_deleted, deleted_at)
) ENGINE=InnoDB;

-- Tabella activity log
CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    resource_type VARCHAR(50) NULL,
    resource_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_tenant_date (tenant_id, created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- Tabella calendari
CREATE TABLE IF NOT EXISTS calendars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    color VARCHAR(7) DEFAULT '#2563EB',
    is_default BOOLEAN DEFAULT FALSE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Tabella eventi
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    calendar_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    location VARCHAR(255) NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    all_day BOOLEAN DEFAULT FALSE,
    recurrence_rule VARCHAR(500) NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (calendar_id) REFERENCES calendars(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_dates (start_datetime, end_datetime)
) ENGINE=InnoDB;

-- Tabella partecipanti eventi
CREATE TABLE IF NOT EXISTS event_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
    responded_at TIMESTAMP NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_participant (event_id, user_id)
) ENGINE=InnoDB;

-- Tabella liste task
CREATE TABLE IF NOT EXISTS task_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) DEFAULT '#6B7280',
    position INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Tabella task
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    task_list_id INT NOT NULL,
    parent_task_id INT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT NULL,
    status ENUM('todo', 'in_progress', 'done', 'cancelled') DEFAULT 'todo',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    position INT DEFAULT 0,
    due_date DATE NULL,
    assigned_to INT NULL,
    created_by INT NOT NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (task_list_id) REFERENCES task_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (task_list_id, status),
    INDEX idx_assigned (assigned_to, status)
) ENGINE=InnoDB;

-- Tabella commenti task
CREATE TABLE IF NOT EXISTS task_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Tabella canali chat
CREATE TABLE IF NOT EXISTS chat_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    type ENUM('public', 'private', 'direct') NOT NULL,
    name VARCHAR(255) NULL,
    description TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Tabella membri canale
CREATE TABLE IF NOT EXISTS chat_channel_members (
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_read_message_id INT NULL,
    PRIMARY KEY (channel_id, user_id),
    FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Tabella messaggi chat
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    parent_message_id INT NULL,
    is_edited BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (parent_message_id) REFERENCES chat_messages(id) ON DELETE SET NULL,
    INDEX idx_channel_time (channel_id, created_at)
) ENGINE=InnoDB;

-- Tabella presenza chat
CREATE TABLE IF NOT EXISTS chat_presence (
    user_id INT PRIMARY KEY,
    tenant_id INT NOT NULL,
    status ENUM('online', 'away', 'offline') DEFAULT 'online',
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_typing_in_channel INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (is_typing_in_channel) REFERENCES chat_channels(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabella link condivisione
CREATE TABLE IF NOT EXISTS share_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    resource_type ENUM('file', 'folder') NOT NULL,
    resource_id INT NOT NULL,
    share_token VARCHAR(64) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NULL,
    expires_at TIMESTAMP NULL,
    max_downloads INT NULL,
    download_count INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_token (share_token)
) ENGINE=InnoDB;

-- Tabella accessi share
CREATE TABLE IF NOT EXISTS share_access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    share_link_id INT NOT NULL,
    action ENUM('view', 'download') NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (share_link_id) REFERENCES share_links(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabella versioni file
CREATE TABLE IF NOT EXISTS file_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_id INT NOT NULL,
    version_number INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    size BIGINT NOT NULL,
    hash_sha256 VARCHAR(64) NOT NULL,
    change_summary TEXT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    UNIQUE KEY unique_version (file_id, version_number)
) ENGINE=InnoDB;

-- Tabella commenti file
CREATE TABLE IF NOT EXISTS file_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    parent_comment_id INT NULL,
    x_position INT NULL,
    y_position INT NULL,
    page_number INT NULL,
    status ENUM('open', 'resolved') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (parent_comment_id) REFERENCES file_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabella workflow approvazione
CREATE TABLE IF NOT EXISTS approval_workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    steps JSON NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Tabella richieste approvazione
CREATE TABLE IF NOT EXISTS approval_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    file_id INT NOT NULL,
    current_step INT DEFAULT 1,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_by INT NOT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id),
    FOREIGN KEY (file_id) REFERENCES files(id),
    FOREIGN KEY (requested_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Tabella dashboard
CREATE TABLE IF NOT EXISTS dashboards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    layout JSON NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabella widget dashboard
CREATE TABLE IF NOT EXISTS dashboard_widgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dashboard_id INT NOT NULL,
    widget_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    position JSON NOT NULL,
    config JSON NOT NULL,
    refresh_interval INT DEFAULT 300,
    FOREIGN KEY (dashboard_id) REFERENCES dashboards(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabella notifiche
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON NULL,
    action_url VARCHAR(500) NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read)
) ENGINE=InnoDB;

-- Tabella preferenze notifiche
CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id INT PRIMARY KEY,
    email_enabled BOOLEAN DEFAULT TRUE,
    email_frequency ENUM('immediate', 'daily', 'weekly') DEFAULT 'immediate',
    quiet_hours_start TIME NULL,
    quiet_hours_end TIME NULL,
    categories JSON DEFAULT '{}',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabella report
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    template VARCHAR(50) NOT NULL,
    parameters JSON NOT NULL,
    schedule JSON NULL,
    recipients JSON NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Tabella metriche
CREATE TABLE IF NOT EXISTS metrics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(20,4) NOT NULL,
    date DATE NOT NULL,
    hour TINYINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_metric (tenant_id, metric_name, date, hour),
    INDEX idx_date (date)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- Dati demo
INSERT INTO tenants (code, name, settings) VALUES
('DEMO', 'Demo Company', '{"max_users": 10, "features": ["files", "folders"]}'),
('FBYTE', 'Fortibyte S.r.l.', '{"max_users": 50, "features": ["files", "folders", "chat", "tasks", "calendar"]}');

-- Password hash: Ricord@1991
INSERT INTO users (tenant_id, email, password_hash, name, role) VALUES
(2, 'asamodeo@fortibyte.it', '$2y$12$bQ.0Rdny.La10ymKlL3MYOJ3mjpYtfmDnUZ3ZQdWbcXMyLHWn5TYi', 'Antonio Samodeo', 'admin');

-- Password hash: admin123
INSERT INTO users (tenant_id, email, password_hash, name, role) VALUES
(1, 'admin@demo.com', '$2y$12$NFifnxwuyywfI/6K1jjCyeBgaM2zRDELdoLuw7aOdTLbWJ/bsFqX6', 'Admin Demo', 'admin'),
(1, 'special@demo.com', '$2y$12$NFifnxwuyywfI/6K1jjCyeBgaM2zRDELdoLuw7aOdTLbWJ/bsFqX6', 'Utente Speciale', 'special'),
(1, 'user@demo.com', '$2y$12$NFifnxwuyywfI/6K1jjCyeBgaM2zRDELdoLuw7aOdTLbWJ/bsFqX6', 'Utente Normale', 'user');

INSERT INTO folders (tenant_id, parent_id, name, path, created_by) VALUES
(1, NULL, 'Documenti', '/documenti/', 1),
(1, NULL, 'Progetti', '/progetti/', 1),
(1, NULL, 'Archivio', '/archivio/', 1);

INSERT INTO calendars (tenant_id, name, color, is_default, created_by) VALUES
(1, 'Calendario Generale', '#2563EB', TRUE, 1);

INSERT INTO task_lists (tenant_id, name, created_by) VALUES
(1, 'Progetto Alpha', 1),
(1, 'Attività Generali', 1);

INSERT INTO chat_channels (tenant_id, type, name, created_by) VALUES
(1, 'public', 'Generale', 1),
(1, 'public', 'Progetti', 1);

INSERT INTO chat_channel_members (channel_id, user_id)
SELECT 1, id FROM users WHERE tenant_id = 1;
