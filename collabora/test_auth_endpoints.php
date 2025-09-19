<?php
/**
 * Authentication Endpoints Test Tool
 * This file helps debug authentication API issues
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Authentication Endpoints - Nexio Collabora</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 800px;
            width: 100%;
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            font-size: 28px;
        }

        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #f9f9f9;
        }

        .test-section h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }

        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 100%;
            margin-top: 10px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        button:active {
            transform: translateY(0);
        }

        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 400px;
            overflow-y: auto;
        }

        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .loading {
            display: none;
            text-align: center;
            margin-top: 10px;
            color: #667eea;
        }

        .endpoint-info {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 13px;
            color: #666;
        }

        .test-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .test-buttons button {
            font-size: 14px;
            padding: 10px 15px;
        }

        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            color: #d63384;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Test Authentication Endpoints</h1>

        <!-- Test auth_simple.php -->
        <div class="test-section">
            <h2>Test auth_simple.php</h2>
            <div class="endpoint-info">
                Endpoint: <code>/api/auth_simple.php</code><br>
                Expected format: JSON with <code>action</code> field
            </div>

            <div class="form-group">
                <label for="simple-action">Action:</label>
                <select id="simple-action">
                    <option value="test">test - Test endpoint</option>
                    <option value="login">login - User login</option>
                    <option value="logout">logout - User logout</option>
                    <option value="check">check - Check authentication</option>
                    <option value="switch_tenant">switch_tenant - Switch tenant</option>
                </select>
            </div>

            <div class="form-group">
                <label for="simple-email">Email (for login):</label>
                <input type="text" id="simple-email" value="asamodeo@fortibyte.it">
            </div>

            <div class="form-group">
                <label for="simple-password">Password (for login):</label>
                <input type="password" id="simple-password" value="Ricord@1991">
            </div>

            <div class="test-buttons">
                <button onclick="testSimpleAuth('json')">Test with JSON</button>
                <button onclick="testSimpleAuth('form')">Test with Form Data</button>
                <button onclick="testSimpleAuth('raw')">Test Raw Request</button>
            </div>

            <div class="loading" id="simple-loading">Loading...</div>
            <div class="result" id="simple-result" style="display:none;"></div>
        </div>

        <!-- Test auth_v2.php -->
        <div class="test-section">
            <h2>Test auth_v2.php</h2>
            <div class="endpoint-info">
                Endpoint: <code>/api/auth_v2.php</code><br>
                Expected format: JSON with <code>action</code> field or URL path
            </div>

            <div class="form-group">
                <label for="v2-action">Action:</label>
                <select id="v2-action">
                    <option value="test">test - Test endpoint</option>
                    <option value="login">login - User login</option>
                    <option value="logout">logout - User logout</option>
                    <option value="me">me - Get current user</option>
                    <option value="tenants">tenants - Get user tenants</option>
                    <option value="switch-tenant">switch-tenant - Switch tenant</option>
                </select>
            </div>

            <div class="form-group">
                <label for="v2-email">Email (for login):</label>
                <input type="text" id="v2-email" value="asamodeo@fortibyte.it">
            </div>

            <div class="form-group">
                <label for="v2-password">Password (for login):</label>
                <input type="password" id="v2-password" value="Ricord@1991">
            </div>

            <div class="test-buttons">
                <button onclick="testV2Auth('post')">Test POST with JSON</button>
                <button onclick="testV2Auth('get')">Test GET Request</button>
                <button onclick="testV2Auth('url')">Test URL Path</button>
            </div>

            <div class="loading" id="v2-loading">Loading...</div>
            <div class="result" id="v2-result" style="display:none;"></div>
        </div>

        <!-- Server Status -->
        <div class="test-section">
            <h2>Server Status</h2>
            <button onclick="checkServerStatus()">Check All Endpoints</button>
            <div class="loading" id="status-loading">Loading...</div>
            <div class="result" id="status-result" style="display:none;"></div>
        </div>
    </div>

    <script>
        // Get base URL dynamically
        function getApiBase() {
            const path = window.location.pathname;
            const parts = path.split('/');
            const collaboraIndex = parts.indexOf('collabora');
            if (collaboraIndex !== -1) {
                return '/' + parts.slice(1, collaboraIndex + 1).join('/') + '/api';
            }
            return '/collabora/api';
        }

        const API_BASE = getApiBase();
        console.log('API Base URL:', API_BASE);

        // Test auth_simple.php
        async function testSimpleAuth(format) {
            const action = document.getElementById('simple-action').value;
            const email = document.getElementById('simple-email').value;
            const password = document.getElementById('simple-password').value;
            const resultDiv = document.getElementById('simple-result');
            const loadingDiv = document.getElementById('simple-loading');

            loadingDiv.style.display = 'block';
            resultDiv.style.display = 'none';

            try {
                let response;
                const url = `${API_BASE}/auth_simple.php`;

                if (format === 'json') {
                    // Test with JSON
                    const payload = { action };
                    if (action === 'login') {
                        payload.email = email;
                        payload.password = password;
                    }

                    console.log('Sending JSON:', payload);
                    response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });
                } else if (format === 'form') {
                    // Test with form data
                    const formData = new FormData();
                    formData.append('action', action);
                    if (action === 'login') {
                        formData.append('email', email);
                        formData.append('password', password);
                    }

                    console.log('Sending Form Data');
                    response = await fetch(url, {
                        method: 'POST',
                        body: formData
                    });
                } else {
                    // Test raw request
                    console.log('Sending raw request to test endpoint');
                    response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ action: 'test' })
                    });
                }

                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    result = { raw_response: text };
                }

                loadingDiv.style.display = 'none';
                resultDiv.style.display = 'block';
                resultDiv.className = response.ok ? 'result success' : 'result error';
                resultDiv.textContent = JSON.stringify(result, null, 2);

                console.log('Response:', result);
            } catch (error) {
                loadingDiv.style.display = 'none';
                resultDiv.style.display = 'block';
                resultDiv.className = 'result error';
                resultDiv.textContent = `Error: ${error.message}`;
                console.error('Error:', error);
            }
        }

        // Test auth_v2.php
        async function testV2Auth(method) {
            const action = document.getElementById('v2-action').value;
            const email = document.getElementById('v2-email').value;
            const password = document.getElementById('v2-password').value;
            const resultDiv = document.getElementById('v2-result');
            const loadingDiv = document.getElementById('v2-loading');

            loadingDiv.style.display = 'block';
            resultDiv.style.display = 'none';

            try {
                let response;
                let url = `${API_BASE}/auth_v2.php`;

                if (method === 'post') {
                    // Test POST with JSON
                    const payload = { action };
                    if (action === 'login') {
                        payload.email = email;
                        payload.password = password;
                    }

                    console.log('Sending POST JSON:', payload);
                    response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });
                } else if (method === 'get') {
                    // Test GET request
                    url = `${API_BASE}/auth_v2.php/${action}`;
                    console.log('Sending GET to:', url);
                    response = await fetch(url, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                } else {
                    // Test URL path method
                    url = `${API_BASE}/auth_v2.php/${action}`;
                    const payload = {};
                    if (action === 'login') {
                        payload.email = email;
                        payload.password = password;
                    }

                    console.log('Sending to URL path:', url, payload);
                    response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });
                }

                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    result = { raw_response: text };
                }

                loadingDiv.style.display = 'none';
                resultDiv.style.display = 'block';
                resultDiv.className = response.ok ? 'result success' : 'result error';
                resultDiv.textContent = JSON.stringify(result, null, 2);

                console.log('Response:', result);
            } catch (error) {
                loadingDiv.style.display = 'none';
                resultDiv.style.display = 'block';
                resultDiv.className = 'result error';
                resultDiv.textContent = `Error: ${error.message}`;
                console.error('Error:', error);
            }
        }

        // Check server status
        async function checkServerStatus() {
            const resultDiv = document.getElementById('status-result');
            const loadingDiv = document.getElementById('status-loading');

            loadingDiv.style.display = 'block';
            resultDiv.style.display = 'none';

            const endpoints = [
                { name: 'auth_simple.php', url: `${API_BASE}/auth_simple.php`, method: 'POST', body: { action: 'test' } },
                { name: 'auth_v2.php', url: `${API_BASE}/auth_v2.php`, method: 'POST', body: { action: 'test' } },
                { name: 'auth_v2.php/test', url: `${API_BASE}/auth_v2.php/test`, method: 'POST', body: {} }
            ];

            const results = [];

            for (const endpoint of endpoints) {
                try {
                    const response = await fetch(endpoint.url, {
                        method: endpoint.method,
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(endpoint.body)
                    });

                    const text = await response.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        data = { raw: text };
                    }

                    results.push({
                        endpoint: endpoint.name,
                        status: response.status,
                        statusText: response.statusText,
                        success: response.ok,
                        response: data
                    });
                } catch (error) {
                    results.push({
                        endpoint: endpoint.name,
                        status: 'ERROR',
                        error: error.message
                    });
                }
            }

            loadingDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.className = 'result info';
            resultDiv.textContent = JSON.stringify(results, null, 2);
        }

        // Test on page load
        window.addEventListener('DOMContentLoaded', () => {
            console.log('Test page loaded');
            console.log('API Base URL:', API_BASE);
        });
    </script>
</body>
</html>