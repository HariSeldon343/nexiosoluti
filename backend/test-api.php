<?php
/**
 * Script di test API per NexioSolution
 * Esegui con: php test-api.php
 */

echo "================================================\n";
echo "     TEST API NEXIOSOLUTION\n";
echo "================================================\n\n";

$baseUrl = 'http://localhost:8000/api';

// Test 1: Health Check
echo "1. Test Health Check:\n";
$ch = curl_init($baseUrl . '/v1/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "   [OK] Health check risponde correttamente\n";
    $data = json_decode($response, true);
    echo "   Status: " . $data['status'] . "\n";
} else {
    echo "   [ERRORE] Health check non risponde (HTTP $httpCode)\n";
}

echo "\n";

// Test 2: Login
echo "2. Test Login con utente demo:\n";
$loginData = [
    'email' => 'admin@nexiosolution.com',
    'password' => 'password123'
];

$ch = curl_init($baseUrl . '/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "   [OK] Login effettuato con successo\n";
    $data = json_decode($response, true);
    if (isset($data['token'])) {
        echo "   Token ricevuto: " . substr($data['token'], 0, 20) . "...\n";
        $token = $data['token'];

        // Test 3: Get User con token
        echo "\n3. Test Get User con token:\n";
        $ch = curl_init($baseUrl . '/user');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            echo "   [OK] Dati utente recuperati\n";
            $userData = json_decode($response, true);
            if (isset($userData['user'])) {
                echo "   Nome: " . $userData['user']['name'] . "\n";
                echo "   Email: " . $userData['user']['email'] . "\n";
            }
        } else {
            echo "   [ERRORE] Impossibile recuperare dati utente (HTTP $httpCode)\n";
        }

        // Test 4: Dashboard Stats
        echo "\n4. Test Dashboard Stats:\n";
        $ch = curl_init($baseUrl . '/dashboard/stats');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            echo "   [OK] Stats dashboard recuperate\n";
            $stats = json_decode($response, true);
            echo "   Companies: " . $stats['companies'] . "\n";
            echo "   Users: " . $stats['users'] . "\n";
            echo "   Tasks: " . $stats['tasks'] . "\n";
        } else {
            echo "   [ERRORE] Impossibile recuperare stats (HTTP $httpCode)\n";
        }
    }
} else {
    echo "   [ERRORE] Login fallito (HTTP $httpCode)\n";
    echo "   Assicurati che il database sia migrato e il seeder eseguito\n";
}

echo "\n================================================\n";
echo "     TEST COMPLETATO\n";
echo "================================================\n\n";

if ($httpCode !== 200) {
    echo "SUGGERIMENTI:\n";
    echo "1. Assicurati che il backend sia attivo su http://localhost:8000\n";
    echo "2. Verifica che MySQL sia attivo\n";
    echo "3. Esegui: php artisan migrate --seed\n";
    echo "4. Controlla i log in storage/logs/laravel.log\n";
}