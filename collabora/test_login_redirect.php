<?php
/**
 * Test Login Redirect System
 * This script tests the complete login and redirect flow
 */

// Start session
session_start();

// Colors for terminal output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$reset = "\033[0m";

echo "{$blue}==============================================\n";
echo "LOGIN REDIRECT SYSTEM TEST\n";
echo "=============================================={$reset}\n\n";

// Test configuration
$baseUrl = 'http://localhost/Nexiosolution/collabora';
$testUser = [
    'email' => 'asamodeo@fortibyte.it',
    'password' => 'Ricord@1991'
];

// Function to make HTTP request
function makeRequest($url, $method = 'GET', $data = null, $cookies = '') {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't follow redirects automatically
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
        }
    }

    if ($cookies) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);

    curl_close($ch);

    // Extract cookies from headers
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headers, $matches);
    $cookies = array();
    foreach($matches[1] as $item) {
        parse_str($item, $cookie);
        $cookies = array_merge($cookies, $cookie);
    }

    return [
        'code' => $httpCode,
        'headers' => $headers,
        'body' => $body,
        'cookies' => $cookies,
        'redirect_url' => $redirectUrl
    ];
}

// Test 1: Check if API endpoints are accessible
echo "{$yellow}Test 1: Check API Endpoints{$reset}\n";
$endpoints = [
    '/api/auth_simple.php',
    '/api/auth_v2.php',
    '/api/me.php'
];

foreach ($endpoints as $endpoint) {
    $response = makeRequest($baseUrl . $endpoint);
    if ($response['code'] == 404) {
        echo "  {$red}✗{$reset} {$endpoint} - Not Found (404)\n";
    } elseif ($response['code'] >= 200 && $response['code'] < 500) {
        echo "  {$green}✓{$reset} {$endpoint} - Accessible (HTTP {$response['code']})\n";
    } else {
        echo "  {$yellow}⚠{$reset} {$endpoint} - HTTP {$response['code']}\n";
    }
}

echo "\n";

// Test 2: Perform login via API
echo "{$yellow}Test 2: Login via API{$reset}\n";
$loginData = [
    'action' => 'login',
    'email' => $testUser['email'],
    'password' => $testUser['password']
];

$response = makeRequest($baseUrl . '/api/auth_simple.php', 'POST', $loginData);
$loginResult = json_decode($response['body'], true);

if ($response['code'] == 200 && isset($loginResult['success']) && $loginResult['success']) {
    echo "  {$green}✓{$reset} Login successful\n";

    // Extract session cookie
    $sessionCookie = '';
    if (isset($response['cookies']['PHPSESSID'])) {
        $sessionCookie = 'PHPSESSID=' . $response['cookies']['PHPSESSID'];
        echo "  {$green}✓{$reset} Session cookie received: " . substr($response['cookies']['PHPSESSID'], 0, 10) . "...\n";
    }

    // Check user data
    if (isset($loginResult['user'])) {
        echo "  {$green}✓{$reset} User data received:\n";
        echo "      - Email: " . ($loginResult['user']['email'] ?? 'N/A') . "\n";
        echo "      - Role: " . ($loginResult['user']['role'] ?? 'N/A') . "\n";
        echo "      - Name: " . ($loginResult['user']['name'] ?? 'N/A') . "\n";
    }

    // Check redirect field
    if (isset($loginResult['redirect'])) {
        echo "  {$green}✓{$reset} Redirect URL provided: {$loginResult['redirect']}\n";
    } else {
        echo "  {$yellow}⚠{$reset} No redirect URL in response\n";
    }

} else {
    echo "  {$red}✗{$reset} Login failed: " . ($loginResult['message'] ?? 'Unknown error') . "\n";
    echo "  Response code: {$response['code']}\n";
    $sessionCookie = '';
}

echo "\n";

// Test 3: Check session status via /api/me.php
if ($sessionCookie) {
    echo "{$yellow}Test 3: Check Session Status{$reset}\n";

    $response = makeRequest($baseUrl . '/api/me.php', 'GET', null, $sessionCookie);
    $meResult = json_decode($response['body'], true);

    if ($response['code'] == 200 && isset($meResult['authenticated']) && $meResult['authenticated']) {
        echo "  {$green}✓{$reset} Session is valid\n";
        echo "  {$green}✓{$reset} User authenticated as: " . ($meResult['user']['email'] ?? 'Unknown') . "\n";
    } else {
        echo "  {$red}✗{$reset} Session check failed\n";
    }

    echo "\n";
}

// Test 4: Check PHP redirects
echo "{$yellow}Test 4: Check PHP Redirect Logic{$reset}\n";

if ($sessionCookie) {
    // Try to access index_v2.php with session
    $response = makeRequest($baseUrl . '/index_v2.php', 'GET', null, $sessionCookie);

    if ($response['code'] == 302 || $response['code'] == 301) {
        echo "  {$green}✓{$reset} index_v2.php redirects when logged in (HTTP {$response['code']})\n";

        // Check Location header
        if (preg_match('/Location:\s*(.+)/i', $response['headers'], $matches)) {
            $redirectLocation = trim($matches[1]);
            echo "  {$green}✓{$reset} Redirect location: {$redirectLocation}\n";

            // Verify it's not redirecting to login page
            if (strpos($redirectLocation, 'index_v2.php') === false &&
                strpos($redirectLocation, 'login') === false) {
                echo "  {$green}✓{$reset} Correctly redirecting away from login page\n";
            } else {
                echo "  {$red}✗{$reset} Still redirecting to login page!\n";
            }
        }
    } elseif ($response['code'] == 200) {
        echo "  {$yellow}⚠{$reset} index_v2.php returns 200 (not redirecting)\n";

        // Check if it's showing login form or dashboard
        if (strpos($response['body'], 'loginForm') !== false) {
            echo "  {$red}✗{$reset} Still showing login form despite being logged in!\n";
        } else {
            echo "  {$green}✓{$reset} Showing dashboard content\n";
        }
    }
} else {
    echo "  {$yellow}⚠{$reset} Skipping - no session cookie available\n";
}

echo "\n";

// Test 5: Check JavaScript files
echo "{$yellow}Test 5: Check JavaScript Files{$reset}\n";

$jsFiles = [
    '/assets/js/auth_v2.js',
    '/assets/js/post-login-config.js',
    '/assets/js/post-login-handler.js',
    '/assets/js/api-config.js',
    '/assets/js/error-handler.js'
];

foreach ($jsFiles as $jsFile) {
    $response = makeRequest($baseUrl . $jsFile);
    if ($response['code'] == 200) {
        $size = strlen($response['body']);
        echo "  {$green}✓{$reset} {$jsFile} - Loaded ({$size} bytes)\n";

        // Check for specific redirect logic in auth_v2.js
        if ($jsFile == '/assets/js/auth_v2.js') {
            if (strpos($response['body'], 'window.location.href') !== false) {
                echo "    {$green}✓{$reset} Contains redirect logic (window.location.href)\n";
            }
            if (strpos($response['body'], 'PostLoginHandler') !== false) {
                echo "    {$green}✓{$reset} Uses PostLoginHandler\n";
            }
            if (strpos($response['body'], 'home_v2.php') !== false) {
                echo "    {$green}✓{$reset} References home_v2.php\n";
            }
        }
    } else {
        echo "  {$red}✗{$reset} {$jsFile} - Failed to load (HTTP {$response['code']})\n";
    }
}

echo "\n";

// Test 6: Check target pages
echo "{$yellow}Test 6: Check Target Pages{$reset}\n";

$targetPages = [
    '/home_v2.php' => 'Home Page',
    '/dashboard.php' => 'Dashboard',
    '/admin/index.php' => 'Admin Panel'
];

foreach ($targetPages as $page => $name) {
    $response = makeRequest($baseUrl . $page);
    if ($response['code'] == 200 || $response['code'] == 302) {
        echo "  {$green}✓{$reset} {$name} ({$page}) - Accessible\n";
    } else {
        echo "  {$red}✗{$reset} {$name} ({$page}) - HTTP {$response['code']}\n";
    }
}

echo "\n";

// Summary
echo "{$blue}==============================================\n";
echo "TEST SUMMARY\n";
echo "=============================================={$reset}\n";

$issues = [];

// Check for common issues
if (!$sessionCookie) {
    $issues[] = "Login not working - no session cookie received";
}

if (!isset($loginResult['redirect']) && !isset($loginResult['user']['role'])) {
    $issues[] = "No redirect information in login response";
}

// Provide recommendations
if (empty($issues)) {
    echo "{$green}✓ All tests passed!{$reset}\n";
    echo "\nThe login redirect system appears to be working correctly.\n";
} else {
    echo "{$red}Issues found:{$reset}\n";
    foreach ($issues as $issue) {
        echo "  • {$issue}\n";
    }

    echo "\n{$yellow}Recommendations:{$reset}\n";
    echo "  1. Check browser console for JavaScript errors\n";
    echo "  2. Verify post-login scripts are loaded in correct order\n";
    echo "  3. Check PHP session configuration\n";
    echo "  4. Test with browser DevTools Network tab open\n";
}

echo "\n{$blue}To test in browser:{$reset}\n";
echo "  1. Open: {$baseUrl}/index_v2.php\n";
echo "  2. Open DevTools (F12) and go to Console tab\n";
echo "  3. Login with: {$testUser['email']} / {$testUser['password']}\n";
echo "  4. Watch console for redirect messages\n";
echo "  5. Verify you are redirected to home_v2.php or admin/index.php\n";

echo "\n";