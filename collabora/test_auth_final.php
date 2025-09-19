<?php
/**
 * Test finale completo per il sistema di autenticazione
 * Verifica sia auth_simple.php che auth_v2.php
 *
 * @author Nexiosolution
 * @version 1.0.0
 * @date 2025-01-18
 */

// Configurazione test
$baseUrl = 'http://localhost/Nexiosolution/collabora';
$testCredentials = [
    'valid' => [
        'email' => 'asamodeo@fortibyte.it',
        'password' => 'Ricord@1991'
    ],
    'invalid' => [
        'email' => 'test@example.com',
        'password' => 'wrongpassword'
    ]
];

// Colori per output
$colors = [
    'success' => "\033[0;32m",
    'error' => "\033[0;31m",
    'warning' => "\033[0;33m",
    'info' => "\033[0;36m",
    'reset' => "\033[0m"
];

// Funzione per output colorato
function printColored($message, $type = 'info') {
    global $colors;
    $color = $colors[$type] ?? $colors['info'];
    echo $color . $message . $colors['reset'] . "\n";
}

// Funzione per test API
function testAPI($endpoint, $data, $method = 'POST') {
    $ch = curl_init($endpoint);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_VERBOSE => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'success' => !$error && $httpCode == 200,
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
        'raw' => $response,
        'error' => $error
    ];
}

// Header HTML
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Autenticazione Finale - Nexio Solution</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            color: white;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .test-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .test-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }

        .test-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-success {
            background: #d4edda;
            color: #155724;
        }

        .status-error {
            background: #f8d7da;
            color: #721c24;
        }

        .status-warning {
            background: #fff3cd;
            color: #856404;
        }

        .status-pending {
            background: #e7e7e7;
            color: #666;
        }

        .test-details {
            display: grid;
            gap: 15px;
        }

        .test-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }

        .test-item.success {
            border-left-color: #28a745;
        }

        .test-item.error {
            border-left-color: #dc3545;
        }

        .test-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .test-value {
            color: #333;
            word-break: break-all;
        }

        .json-output {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
        }

        .summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            text-align: center;
        }

        .summary h2 {
            margin-bottom: 15px;
        }

        .summary-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Test Sistema Autenticazione</h1>

        <?php
        $totalTests = 0;
        $passedTests = 0;
        $failedTests = 0;

        // Test 1: auth_simple.php con credenziali valide
        ?>
        <div class="test-section">
            <div class="test-header">
                <div class="test-title">Test 1: auth_simple.php - Login con credenziali valide</div>
                <?php
                $test1 = testAPI($baseUrl . '/api/auth_simple.php', [
                    'action' => 'login',
                    'email' => $testCredentials['valid']['email'],
                    'password' => $testCredentials['valid']['password']
                ]);
                $totalTests++;

                if ($test1['success'] && $test1['response']['success']) {
                    $passedTests++;
                    echo '<div class="test-status status-success">✓ PASSED</div>';
                } else {
                    $failedTests++;
                    echo '<div class="test-status status-error">✗ FAILED</div>';
                }
                ?>
            </div>
            <div class="test-details">
                <div class="test-item <?php echo $test1['success'] ? 'success' : 'error'; ?>">
                    <div class="test-label">Endpoint:</div>
                    <div class="test-value"><?php echo $baseUrl . '/api/auth_simple.php'; ?></div>
                </div>
                <div class="test-item">
                    <div class="test-label">HTTP Status Code:</div>
                    <div class="test-value"><?php echo $test1['http_code']; ?></div>
                </div>
                <div class="test-item">
                    <div class="test-label">Response:</div>
                    <div class="json-output"><?php echo json_encode($test1['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></div>
                </div>
            </div>
        </div>

        <?php
        // Test 2: auth_simple.php con credenziali invalide
        ?>
        <div class="test-section">
            <div class="test-header">
                <div class="test-title">Test 2: auth_simple.php - Login con credenziali invalide</div>
                <?php
                $test2 = testAPI($baseUrl . '/api/auth_simple.php', [
                    'action' => 'login',
                    'email' => $testCredentials['invalid']['email'],
                    'password' => $testCredentials['invalid']['password']
                ]);
                $totalTests++;

                if (!$test2['success'] || !$test2['response']['success']) {
                    $passedTests++;
                    echo '<div class="test-status status-success">✓ PASSED (Errore atteso)</div>';
                } else {
                    $failedTests++;
                    echo '<div class="test-status status-error">✗ FAILED</div>';
                }
                ?>
            </div>
            <div class="test-details">
                <div class="test-item <?php echo (!$test2['success'] || !$test2['response']['success']) ? 'success' : 'error'; ?>">
                    <div class="test-label">Endpoint:</div>
                    <div class="test-value"><?php echo $baseUrl . '/api/auth_simple.php'; ?></div>
                </div>
                <div class="test-item">
                    <div class="test-label">HTTP Status Code:</div>
                    <div class="test-value"><?php echo $test2['http_code']; ?></div>
                </div>
                <div class="test-item">
                    <div class="test-label">Response:</div>
                    <div class="json-output"><?php echo json_encode($test2['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></div>
                </div>
            </div>
        </div>

        <?php
        // Test 3: auth_v2.php con credenziali valide
        ?>
        <div class="test-section">
            <div class="test-header">
                <div class="test-title">Test 3: auth_v2.php - Login con credenziali valide</div>
                <?php
                $test3 = testAPI($baseUrl . '/api/auth_v2.php', [
                    'action' => 'login',
                    'email' => $testCredentials['valid']['email'],
                    'password' => $testCredentials['valid']['password']
                ]);
                $totalTests++;

                if ($test3['success'] && $test3['response']['success']) {
                    $passedTests++;
                    echo '<div class="test-status status-success">✓ PASSED</div>';
                } else {
                    $failedTests++;
                    echo '<div class="test-status status-error">✗ FAILED</div>';
                }
                ?>
            </div>
            <div class="test-details">
                <div class="test-item <?php echo $test3['success'] ? 'success' : 'error'; ?>">
                    <div class="test-label">Endpoint:</div>
                    <div class="test-value"><?php echo $baseUrl . '/api/auth_v2.php'; ?></div>
                </div>
                <div class="test-item">
                    <div class="test-label">HTTP Status Code:</div>
                    <div class="test-value"><?php echo $test3['http_code']; ?></div>
                </div>
                <div class="test-item">
                    <div class="test-label">Response:</div>
                    <div class="json-output"><?php echo json_encode($test3['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></div>
                </div>
            </div>
        </div>

        <?php
        // Test 4: auth_v2.php con payload malformato
        ?>
        <div class="test-section">
            <div class="test-header">
                <div class="test-title">Test 4: auth_v2.php - Payload JSON malformato</div>
                <?php
                $test4 = testAPI($baseUrl . '/api/auth_v2.php', [
                    'action' => 'login'
                    // Mancano email e password
                ]);
                $totalTests++;

                if (!$test4['success'] || !$test4['response']['success']) {
                    $passedTests++;
                    echo '<div class="test-status status-success">✓ PASSED (Errore atteso)</div>';
                } else {
                    $failedTests++;
                    echo '<div class="test-status status-error">✗ FAILED</div>';
                }
                ?>
            </div>
            <div class="test-details">
                <div class="test-item <?php echo (!$test4['success'] || !$test4['response']['success']) ? 'success' : 'error'; ?>">
                    <div class="test-label">Endpoint:</div>
                    <div class="test-value"><?php echo $baseUrl . '/api/auth_v2.php'; ?></div>
                </div>
                <div class="test-item">
                    <div class="test-label">HTTP Status Code:</div>
                    <div class="test-value"><?php echo $test4['http_code']; ?></div>
                </div>
                <div class="test-item">
                    <div class="test-label">Response:</div>
                    <div class="json-output"><?php echo json_encode($test4['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></div>
                </div>
            </div>
        </div>

        <?php
        // Test 5: Test endpoint di verifica
        ?>
        <div class="test-section">
            <div class="test-header">
                <div class="test-title">Test 5: Endpoint di test API</div>
                <?php
                $test5 = testAPI($baseUrl . '/api/auth_simple.php', [
                    'action' => 'test'
                ]);
                $totalTests++;

                if ($test5['success'] && $test5['response']['success']) {
                    $passedTests++;
                    echo '<div class="test-status status-success">✓ PASSED</div>';
                } else {
                    $failedTests++;
                    echo '<div class="test-status status-error">✗ FAILED</div>';
                }
                ?>
            </div>
            <div class="test-details">
                <div class="test-item <?php echo $test5['success'] ? 'success' : 'error'; ?>">
                    <div class="test-label">Endpoint:</div>
                    <div class="test-value"><?php echo $baseUrl . '/api/auth_simple.php'; ?></div>
                </div>
                <div class="test-item">
                    <div class="test-label">HTTP Status Code:</div>
                    <div class="test-value"><?php echo $test5['http_code']; ?></div>
                </div>
                <div class="test-item">
                    <div class="test-label">Response:</div>
                    <div class="json-output"><?php echo json_encode($test5['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></div>
                </div>
            </div>
        </div>

        <?php
        // Test 6: auth_simple.php - Check autenticazione
        ?>
        <div class="test-section">
            <div class="test-header">
                <div class="test-title">Test 6: auth_simple.php - Check stato autenticazione</div>
                <?php
                $test6 = testAPI($baseUrl . '/api/auth_simple.php', [
                    'action' => 'check'
                ]);
                $totalTests++;

                if ($test6['success']) {
                    $passedTests++;
                    echo '<div class="test-status status-success">✓ PASSED</div>';
                } else {
                    $failedTests++;
                    echo '<div class="test-status status-error">✗ FAILED</div>';
                }
                ?>
            </div>
            <div class="test-details">
                <div class="test-item <?php echo $test6['success'] ? 'success' : 'error'; ?>">
                    <div class="test-label">Endpoint:</div>
                    <div class="test-value"><?php echo $baseUrl . '/api/auth_simple.php'; ?></div>
                </div>
                <div class="test-item">
                    <div class="test-label">HTTP Status Code:</div>
                    <div class="test-value"><?php echo $test6['http_code']; ?></div>
                </div>
                <div class="test-item">
                    <div class="test-label">Response:</div>
                    <div class="json-output"><?php echo json_encode($test6['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></div>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="summary">
            <h2>📊 Riepilogo Test</h2>
            <div class="summary-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $totalTests; ?></div>
                    <div class="stat-label">Test Totali</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" style="color: #90EE90;"><?php echo $passedTests; ?></div>
                    <div class="stat-label">Test Passati</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" style="color: #FFB6C1;"><?php echo $failedTests; ?></div>
                    <div class="stat-label">Test Falliti</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo round(($passedTests / $totalTests) * 100, 1); ?>%</div>
                    <div class="stat-label">Success Rate</div>
                </div>
            </div>
            <p style="margin-top: 20px; opacity: 0.9;">
                Test completato il <?php echo date('d/m/Y H:i:s'); ?>
            </p>
        </div>
    </div>
</body>
</html>