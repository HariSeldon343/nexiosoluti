<?php
/**
 * Test Script for Error Handling Improvements
 * Tests authentication error handling and messaging
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Error Handling - Nexio Collabora</title>
    <link rel="stylesheet" href="assets/css/auth_v2.css">
    <style>
        .test-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .test-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #E5E7EB;
        }
        .test-section {
            margin: 2rem 0;
            padding: 1.5rem;
            background: #F9FAFB;
            border-radius: 8px;
        }
        .test-button {
            margin: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #2563EB;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        .test-button:hover {
            background: #1D4ED8;
            transform: translateY(-1px);
        }
        .test-button.error {
            background: #EF4444;
        }
        .test-button.error:hover {
            background: #DC2626;
        }
        .test-button.warning {
            background: #F59E0B;
        }
        .test-button.warning:hover {
            background: #D97706;
        }
        .console-output {
            margin-top: 1rem;
            padding: 1rem;
            background: #1F2937;
            color: #10B981;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 0.875rem;
            border-radius: 6px;
            max-height: 300px;
            overflow-y: auto;
        }
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        .status-indicator.success {
            background: #10B981;
        }
        .status-indicator.error {
            background: #EF4444;
        }
        .status-indicator.pending {
            background: #F59E0B;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h1>Error Handling Test Suite</h1>
            <p>Test various error scenarios to verify proper error handling and messaging</p>
        </div>

        <!-- Test Credentials -->
        <div class="test-section">
            <h2>Test Credentials</h2>
            <p><strong>Email:</strong> asamodeo@fortibyte.it</p>
            <p><strong>Password:</strong> Ricord@1991</p>
        </div>

        <!-- Authentication Tests -->
        <div class="test-section">
            <h2>Authentication Tests</h2>

            <button class="test-button" onclick="testValidLogin()">
                Test Valid Login
            </button>

            <button class="test-button error" onclick="testInvalidCredentials()">
                Test Invalid Credentials
            </button>

            <button class="test-button error" onclick="testMissingFields()">
                Test Missing Fields
            </button>

            <button class="test-button error" onclick="testInvalidJSON()">
                Test Invalid JSON
            </button>

            <button class="test-button warning" onclick="test404Endpoint()">
                Test 404 Endpoint
            </button>

            <button class="test-button warning" onclick="testNetworkError()">
                Test Network Error
            </button>

            <button class="test-button" onclick="testDebugMode()">
                Toggle Debug Mode
            </button>

            <div id="auth-console" class="console-output"></div>
        </div>

        <!-- Error Display Tests -->
        <div class="test-section">
            <h2>Error Display Tests</h2>

            <button class="test-button" onclick="showErrorToast()">
                Show Error Toast
            </button>

            <button class="test-button warning" onclick="showWarningToast()">
                Show Warning Toast
            </button>

            <button class="test-button" onclick="showInfoToast()">
                Show Info Toast
            </button>

            <button class="test-button" onclick="showSuccessToast()">
                Show Success Toast
            </button>

            <button class="test-button error" onclick="showErrorDialog()">
                Show Error Dialog
            </button>

            <button class="test-button" onclick="showMultilineError()">
                Show Multiline Error
            </button>
        </div>

        <!-- API Endpoint Status -->
        <div class="test-section">
            <h2>API Endpoint Status</h2>
            <div id="endpoint-status"></div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <!-- Error Display Container -->
    <div id="error-display" class="error-display hidden">
        <div class="error-content">
            <div class="error-header">
                <svg class="error-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <h3 class="error-title">Errore</h3>
                <button class="error-close" onclick="document.getElementById('error-display').classList.add('hidden')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p class="error-message"></p>
            <div class="error-details hidden">
                <pre class="error-debug"></pre>
            </div>
            <div class="error-actions">
                <button class="btn btn-secondary" onclick="location.reload()">Ricarica Pagina</button>
                <button class="btn btn-primary" onclick="document.getElementById('error-display').classList.add('hidden')">Chiudi</button>
            </div>
        </div>
    </div>

    <!-- Debug Toggle -->
    <div id="debug-toggle" class="debug-toggle" title="Toggle Debug Mode">
        <button onclick="testDebugMode()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 12.75c1.148 0 2.278.08 3.383.237a1.037 1.037 0 01.834.82c.044.282.044.566 0 .849a1.037 1.037 0 01-.834.82c-1.105.157-2.235.236-3.383.236-1.148 0-2.278-.08-3.383-.237a1.037 1.037 0 01-.834-.82 2.282 2.282 0 010-.848 1.037 1.037 0 01.834-.821A41.699 41.699 0 0112 12.75zm0 0c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-5.272 0 3.745 3.745 0 01-1.043-3.296A4.124 4.124 0 011.5 12.75c0-2.278 1.843-4.125 4.125-4.125h.458m6.917 0v-.75a3.375 3.375 0 00-3.375-3.375h-.917m6.917 4.125a5.625 5.625 0 011.5 3.825m-7.5-3.825h.459c2.278 0 4.125 1.847 4.125 4.125a4.125 4.125 0 01-1.55 3.218 3.745 3.745 0 011.043 3.296 3.745 3.745 0 005.272 0 3.745 3.745 0 001.043-3.296A4.124 4.124 0 0022.5 12.75c0-2.278-1.843-4.125-4.125-4.125h-.458m-6.917 0V7.875a3.375 3.375 0 013.375-3.375h.917" />
            </svg>
        </button>
        <span class="debug-status"></span>
    </div>

    <!-- Scripts -->
    <script src="assets/js/api-config.js"></script>
    <script src="assets/js/error-handler.js"></script>
    <script src="assets/js/auth_v2.js"></script>

    <script>
        const API_BASE = window.APIConfig ? window.APIConfig.getApiBaseUrl() : '/collabora/api';
        const consoleEl = document.getElementById('auth-console');

        function log(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const color = type === 'error' ? '#EF4444' : type === 'success' ? '#10B981' : '#60A5FA';
            consoleEl.innerHTML += `<div style="color: ${color}">[${timestamp}] ${message}</div>`;
            consoleEl.scrollTop = consoleEl.scrollHeight;
        }

        // Test Functions
        async function testValidLogin() {
            log('Testing valid login...');
            try {
                const response = await window.ErrorHandler.post(`${API_BASE}/auth_simple.php`, {
                    action: 'login',
                    email: 'asamodeo@fortibyte.it',
                    password: 'Ricord@1991'
                });
                const data = await response.json();
                log('Login successful: ' + JSON.stringify(data), 'success');
                window.authV2.showToast('success', 'Login Test', 'Valid credentials accepted');
            } catch (error) {
                log('Login failed: ' + JSON.stringify(error), 'error');
                window.ErrorHandler.showError(error);
            }
        }

        async function testInvalidCredentials() {
            log('Testing invalid credentials...');
            try {
                const response = await window.ErrorHandler.post(`${API_BASE}/auth_simple.php`, {
                    action: 'login',
                    email: 'wrong@email.com',
                    password: 'wrongpassword'
                });
                const data = await response.json();
                log('Unexpected success: ' + JSON.stringify(data), 'error');
            } catch (error) {
                log('Expected error received: ' + error.message, 'success');
                window.ErrorHandler.showError(error);
            }
        }

        async function testMissingFields() {
            log('Testing missing fields...');
            try {
                const response = await window.ErrorHandler.post(`${API_BASE}/auth_simple.php`, {
                    action: 'login'
                    // Missing email and password
                });
                const data = await response.json();
                log('Unexpected success: ' + JSON.stringify(data), 'error');
            } catch (error) {
                log('Expected error received: ' + error.message, 'success');
                window.ErrorHandler.showError(error);
            }
        }

        async function testInvalidJSON() {
            log('Testing invalid JSON...');
            try {
                const response = await fetch(`${API_BASE}/auth_simple.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: 'invalid json {{'
                });

                if (!response.ok) {
                    throw {
                        status: response.status,
                        message: await response.text()
                    };
                }

                const data = await response.json();
                log('Unexpected success: ' + JSON.stringify(data), 'error');
            } catch (error) {
                log('Expected error received: ' + error.message, 'success');
                window.ErrorHandler.showError(error);
            }
        }

        async function test404Endpoint() {
            log('Testing 404 endpoint...');
            try {
                const response = await window.ErrorHandler.post(`${API_BASE}/nonexistent.php`, {
                    action: 'test'
                });
                const data = await response.json();
                log('Unexpected success: ' + JSON.stringify(data), 'error');
            } catch (error) {
                log('Expected 404 error received: ' + error.status, 'success');
                window.ErrorHandler.showError(error);
            }
        }

        async function testNetworkError() {
            log('Testing network error...');
            try {
                // Try to fetch from invalid URL
                const response = await window.ErrorHandler.get('http://invalid.local.test/api');
                const data = await response.json();
                log('Unexpected success: ' + JSON.stringify(data), 'error');
            } catch (error) {
                log('Expected network error received', 'success');
                window.ErrorHandler.showError(error);
            }
        }

        function testDebugMode() {
            const isDebug = localStorage.getItem('debugMode') === 'true';
            window.ErrorHandler.setDebugMode(!isDebug);

            const statusEl = document.querySelector('.debug-status');
            const toggle = document.getElementById('debug-toggle');

            if (!isDebug) {
                statusEl.textContent = 'Debug ON';
                statusEl.style.display = 'block';
                toggle.classList.add('active');
                log('Debug mode enabled', 'success');
            } else {
                statusEl.textContent = '';
                statusEl.style.display = 'none';
                toggle.classList.remove('active');
                log('Debug mode disabled');
            }
        }

        // Toast tests
        function showErrorToast() {
            window.authV2.showToast('error', 'Errore di Test', 'Questo è un messaggio di errore di esempio');
        }

        function showWarningToast() {
            window.authV2.showToast('warning', 'Avviso', 'Questo è un messaggio di avviso');
        }

        function showInfoToast() {
            window.authV2.showToast('info', 'Informazione', 'Questo è un messaggio informativo');
        }

        function showSuccessToast() {
            window.authV2.showToast('success', 'Successo', 'Operazione completata con successo');
        }

        function showErrorDialog() {
            const display = document.getElementById('error-display');
            const title = display.querySelector('.error-title');
            const message = display.querySelector('.error-message');
            const details = display.querySelector('.error-details');
            const debug = display.querySelector('.error-debug');

            title.textContent = 'Errore di Sistema';
            message.textContent = 'Si è verificato un errore critico durante l\'elaborazione della richiesta.';

            if (localStorage.getItem('debugMode') === 'true') {
                details.classList.remove('hidden');
                debug.textContent = JSON.stringify({
                    error: 'DATABASE_CONNECTION_FAILED',
                    status: 500,
                    timestamp: new Date().toISOString(),
                    details: {
                        host: 'localhost',
                        database: 'nexio_collabora_v2',
                        error: 'Connection refused'
                    }
                }, null, 2);
            }

            display.classList.remove('hidden');
        }

        function showMultilineError() {
            const message = `Errore multiplo rilevato:
• Connessione al database fallita
• Token di sessione scaduto
• Permessi insufficienti per l'operazione
• Limite di richieste superato

Contatta l'amministratore per assistenza.`;

            window.authV2.showToast('error', 'Errori Multipli', message);
        }

        // Check endpoint status on load
        async function checkEndpoints() {
            const endpoints = [
                'auth_simple.php',
                'auth_v2.php',
                'users.php',
                'files.php'
            ];

            const statusEl = document.getElementById('endpoint-status');
            statusEl.innerHTML = '<p>Checking endpoints...</p>';

            let html = '<table style="width: 100%">';
            html += '<tr><th>Endpoint</th><th>Status</th><th>Response</th></tr>';

            for (const endpoint of endpoints) {
                try {
                    const response = await fetch(`${API_BASE}/${endpoint}`, {
                        method: 'OPTIONS'
                    });

                    const status = response.status;
                    const statusText = status === 200 ? 'OK' : status === 404 ? 'Not Found' : `${status}`;
                    const indicator = status === 200 ? 'success' : status === 404 ? 'error' : 'pending';

                    html += `<tr>
                        <td>${endpoint}</td>
                        <td><span class="status-indicator ${indicator}"></span>${statusText}</td>
                        <td>${response.statusText || '-'}</td>
                    </tr>`;
                } catch (error) {
                    html += `<tr>
                        <td>${endpoint}</td>
                        <td><span class="status-indicator error"></span>Error</td>
                        <td>${error.message}</td>
                    </tr>`;
                }
            }

            html += '</table>';
            statusEl.innerHTML = html;
        }

        // Initialize debug status
        document.addEventListener('DOMContentLoaded', function() {
            const isDebug = localStorage.getItem('debugMode') === 'true';
            const statusEl = document.querySelector('.debug-status');
            const toggle = document.getElementById('debug-toggle');

            if (statusEl) {
                statusEl.textContent = isDebug ? 'Debug ON' : '';
                statusEl.style.display = isDebug ? 'block' : 'none';
            }

            if (toggle) {
                toggle.classList.toggle('active', isDebug);
            }

            // Check endpoints
            checkEndpoints();

            log('Test suite initialized. Debug mode: ' + (isDebug ? 'ON' : 'OFF'));
        });
    </script>
</body>
</html>