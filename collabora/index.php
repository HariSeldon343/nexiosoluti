<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();
$user = $auth->check();
$csrfToken = getCsrfToken();
$initialState = [
    'csrfToken' => $csrfToken,
    'user' => $user,
];
?><!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collabora Platform</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <noscript>
        <div class="no-js">Per utilizzare Collabora è necessario abilitare JavaScript.</div>
    </noscript>

    <!-- Login Form -->
    <div id="login-panel" class="login-container<?= $user ? ' hidden' : ''; ?>">
        <form id="login-form" autocomplete="off">
            <h2>Accedi a Collabora</h2>
            <input type="email" id="email" placeholder="Email" required>
            <input type="password" id="password" placeholder="Password" required>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit">Accedi</button>
        </form>
    </div>

    <!-- Dashboard -->
    <div id="dashboard" class="<?= $user ? '' : 'hidden'; ?>">
        <aside id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3>Collabora</h3>
                <button id="sidebar-toggle" type="button" aria-label="Apri/chiudi menu">☰</button>
            </div>
            <nav class="sidebar-nav">
                <a href="#" data-page="files" class="nav-item active">
                    <span class="icon">📁</span>
                    <span>File</span>
                </a>
                <a href="#" data-page="calendar" class="nav-item">
                    <span class="icon">📅</span>
                    <span>Calendario</span>
                </a>
                <a href="#" data-page="tasks" class="nav-item">
                    <span class="icon">🗂</span>
                    <span>Task</span>
                </a>
                <a href="#" data-page="chat" class="nav-item">
                    <span class="icon">💬</span>
                    <span>Chat</span>
                </a>
                <a href="#" data-page="sharing" class="nav-item">
                    <span class="icon">🔗</span>
                    <span>Sharing</span>
                </a>
                <a href="#" data-page="dashboard-home" class="nav-item">
                    <span class="icon">📊</span>
                    <span>Dashboard</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="tenant-switch" id="tenant-switch"></div>
                <div class="user-info">
                    <span id="user-name"></span>
                    <span id="user-role"></span>
                </div>
                <button id="logout" type="button">Esci</button>
            </div>
        </aside>

        <main id="main-content">
            <div id="files-page" class="page<?= $user ? '' : ' hidden'; ?>">
                <header class="page-header">
                    <div class="breadcrumb" id="breadcrumb">
                        <a href="#" data-folder="null">Home</a>
                    </div>
                    <div class="actions">
                        <button id="upload-btn" type="button">Upload</button>
                        <button id="new-folder-btn" type="button">Nuova Cartella</button>
                        <button id="view-toggle" type="button">Vista</button>
                    </div>
                </header>
                <div class="file-container">
                    <div class="folder-tree" id="folder-tree"></div>
                    <div class="file-grid" id="file-grid"></div>
                </div>
                <input type="file" id="file-input" multiple class="hidden" />
                <div id="drop-zone" class="drop-zone hidden">Trascina i file qui</div>
            </div>

            <div id="calendar-page" class="page hidden">
                <header class="page-header">
                    <div class="calendar-nav">
                        <button id="cal-prev" type="button">◄</button>
                        <h2 id="cal-month">Gennaio 2024</h2>
                        <button id="cal-next" type="button">►</button>
                    </div>
                    <div class="calendar-views">
                        <button class="view-btn active" data-view="month" type="button">Mese</button>
                        <button class="view-btn" data-view="week" type="button">Settimana</button>
                        <button class="view-btn" data-view="day" type="button">Giorno</button>
                    </div>
                    <button id="new-event" type="button">+ Evento</button>
                </header>
                <div id="calendar-grid" class="calendar-grid"></div>
            </div>

            <div id="tasks-page" class="page hidden">
                <header class="page-header">
                    <select id="task-list-select"></select>
                    <button id="new-task" type="button">+ Task</button>
                </header>
                <div class="kanban-board">
                    <div class="kanban-column" data-status="todo">
                        <h3>Da Fare <span class="count">(0)</span></h3>
                        <div class="kanban-cards"></div>
                    </div>
                    <div class="kanban-column" data-status="in_progress">
                        <h3>In Corso <span class="count">(0)</span></h3>
                        <div class="kanban-cards"></div>
                    </div>
                    <div class="kanban-column" data-status="done">
                        <h3>Completati <span class="count">(0)</span></h3>
                        <div class="kanban-cards"></div>
                    </div>
                </div>
            </div>

            <div id="chat-page" class="page hidden">
                <div class="chat-layout">
                    <div class="chat-channels" id="chat-channels"></div>
                    <div class="chat-conversation">
                        <div class="chat-header">
                            <h3 id="chat-channel-title">Chat</h3>
                        </div>
                        <div class="chat-messages" id="chat-messages"></div>
                        <form id="chat-form">
                            <textarea id="chat-input" rows="2" placeholder="Scrivi un messaggio..."></textarea>
                            <button type="submit">Invia</button>
                        </form>
                    </div>
                </div>
            </div>

            <div id="sharing-page" class="page hidden">
                <header class="page-header">
                    <h2>Condivisioni</h2>
                    <button id="new-share-link" type="button">+ Link</button>
                </header>
                <div id="share-list" class="share-list"></div>
            </div>

            <div id="dashboard-home" class="page hidden">
                <header class="page-header">
                    <h2>Dashboard personalizzate</h2>
                    <button id="new-widget" type="button">+ Widget</button>
                </header>
                <div id="dashboard-widgets" class="dashboard-grid"></div>
            </div>
        </main>
    </div>

    <div id="toast-container"></div>
    <div id="preview-modal" class="modal hidden"></div>
    <div id="folder-modal" class="modal hidden"></div>

    <script>
        window.__COLLABORA__ = <?= json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="assets/js/app.js" type="module"></script>
    <script src="assets/js/filemanager.js" type="module"></script>
    <script src="assets/js/calendar.js" type="module"></script>
    <script src="assets/js/tasks.js" type="module"></script>
    <script src="assets/js/chat.js" type="module"></script>
    <script src="assets/js/polling.js" type="module"></script>
    <script src="assets/js/sharing.js" type="module"></script>
    <script src="assets/js/comments.js" type="module"></script>
    <script src="assets/js/dashboard.js" type="module"></script>
    <script src="assets/js/notifications.js" type="module"></script>
</body>
</html>
