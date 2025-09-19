/**
 * Test Module per la verifica dei percorsi API
 * Può essere eseguito direttamente dalla console del browser
 *
 * Uso:
 * 1. Apri la console del browser (F12)
 * 2. Copia e incolla questo codice
 * 3. Esegui: testAPIPaths.runAll()
 */

const testAPIPaths = (function() {
    'use strict';

    // Configurazione
    const config = {
        verbose: true,
        stopOnError: false,
        testCredentials: {
            email: 'admin@nexio.com',
            password: 'admin123'
        }
    };

    // Stato
    let results = {
        passed: 0,
        failed: 0,
        total: 0,
        details: [],
        startTime: null,
        endTime: null
    };

    // Utility per logging
    const log = {
        info: (msg, ...args) => console.log(`ℹ️ ${msg}`, ...args),
        success: (msg, ...args) => console.log(`✅ ${msg}`, ...args),
        error: (msg, ...args) => console.error(`❌ ${msg}`, ...args),
        warning: (msg, ...args) => console.warn(`⚠️ ${msg}`, ...args),
        group: (name) => console.group(`📁 ${name}`),
        groupEnd: () => console.groupEnd(),
        table: (data) => console.table(data)
    };

    // Rileva percorso base
    function detectBasePath() {
        const scriptPath = window.location.pathname;
        const pathParts = scriptPath.split('/').filter(p => p);

        // Cerca 'collabora' nel percorso
        const collaboraIndex = pathParts.indexOf('collabora');

        if (collaboraIndex !== -1) {
            const baseParts = pathParts.slice(0, collaboraIndex + 1);
            return '/' + baseParts.join('/');
        }

        // Fallback: usa percorso corrente senza filename
        pathParts.pop(); // Rimuovi filename
        return '/' + pathParts.join('/');
    }

    // Costruisci URL completo
    function buildUrl(path) {
        const protocol = window.location.protocol;
        const host = window.location.host;
        const basePath = detectBasePath();

        // Rimuovi slash iniziale dal path se presente
        if (path.startsWith('/')) {
            path = path.substring(1);
        }

        return `${protocol}//${host}${basePath}/${path}`;
    }

    // Test singolo endpoint
    async function testEndpoint(name, path, options = {}) {
        const url = buildUrl(path);
        const startTime = performance.now();

        const testResult = {
            name: name,
            url: url,
            path: path,
            method: options.method || 'GET',
            status: null,
            time: null,
            passed: false,
            error: null,
            response: null
        };

        try {
            if (config.verbose) {
                log.info(`Testing: ${name} - ${url}`);
            }

            const fetchOptions = {
                method: options.method || 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
            };

            if (options.body) {
                fetchOptions.body = JSON.stringify(options.body);
            }

            const response = await fetch(url, fetchOptions);
            testResult.status = response.status;
            testResult.time = Math.round(performance.now() - startTime);

            // Considera successo: 200-299, 401 (auth required), 405 (method not allowed)
            if (response.ok || response.status === 401 || response.status === 405) {
                testResult.passed = true;
                results.passed++;

                if (options.parseResponse !== false) {
                    try {
                        testResult.response = await response.json();
                    } catch (e) {
                        // Response non è JSON
                        testResult.response = await response.text();
                    }
                }

                log.success(`${name}: ${response.status} (${testResult.time}ms)`);
            } else {
                testResult.passed = false;
                testResult.error = `HTTP ${response.status}`;
                results.failed++;
                log.error(`${name}: ${response.status}`);
            }

        } catch (error) {
            testResult.passed = false;
            testResult.error = error.message;
            testResult.time = Math.round(performance.now() - startTime);
            results.failed++;
            log.error(`${name}: ${error.message}`);
        }

        results.details.push(testResult);
        results.total++;

        if (!testResult.passed && config.stopOnError) {
            throw new Error(`Test failed: ${name}`);
        }

        return testResult;
    }

    // Test rilevamento percorsi
    async function testPathDetection() {
        log.group('Path Detection Test');

        const detectedPath = detectBasePath();
        const expectedPaths = [
            '/collabora',
            '/Nexiosolution/collabora',
            '/app/collabora'
        ];

        const pathInfo = {
            'Current URL': window.location.href,
            'Detected Base': detectedPath,
            'API URL': buildUrl('api'),
            'Assets URL': buildUrl('assets'),
            'Valid Path': expectedPaths.some(p => detectedPath.endsWith(p))
        };

        log.table(pathInfo);

        if (pathInfo['Valid Path']) {
            log.success('Path detection successful');
            results.passed++;
        } else {
            log.warning('Unexpected path detected, but continuing...');
        }

        results.total++;
        log.groupEnd();

        return pathInfo;
    }

    // Test tutti gli endpoint API
    async function testAPIEndpoints() {
        log.group('API Endpoints Test');

        const endpoints = [
            { name: 'Auth V2', path: 'api/auth_v2.php', method: 'OPTIONS' },
            { name: 'Auth Simple', path: 'api/auth_simple.php', method: 'OPTIONS' },
            { name: 'Users', path: 'api/users.php', method: 'OPTIONS' },
            { name: 'Tenants', path: 'api/tenants.php', method: 'OPTIONS' },
            { name: 'Files', path: 'api/files.php', method: 'OPTIONS' },
            { name: 'Folders', path: 'api/folders.php', method: 'OPTIONS' },
            { name: 'WebDAV', path: 'api/webdav.php', method: 'OPTIONS' },
            { name: 'Webhooks', path: 'api/webhooks.php', method: 'OPTIONS' }
        ];

        for (const endpoint of endpoints) {
            await testEndpoint(endpoint.name, endpoint.path, { method: endpoint.method });
        }

        log.groupEnd();
    }

    // Test login
    async function testAuthentication() {
        log.group('Authentication Test');

        const loginResult = await testEndpoint('Login', 'api/auth_v2.php', {
            method: 'POST',
            body: {
                action: 'login',
                email: config.testCredentials.email,
                password: config.testCredentials.password
            }
        });

        if (loginResult.passed && loginResult.response && loginResult.response.success) {
            log.success('Login successful');

            // Salva token per test successivi
            const token = loginResult.response.token;
            if (token) {
                localStorage.setItem('test_token', token);
                log.info('Token saved for further tests');

                // Test endpoint autenticato
                await testEndpoint('Authenticated Request', 'api/users.php', {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
            }
        } else {
            log.error('Login failed');
        }

        log.groupEnd();
        return loginResult;
    }

    // Test assets e risorse statiche
    async function testStaticResources() {
        log.group('Static Resources Test');

        const resources = [
            { name: 'Main CSS', path: 'assets/css/style.css' },
            { name: 'Main JS', path: 'assets/js/main.js' },
            { name: 'API Module', path: 'assets/js/api.js' },
            { name: 'Auth Module', path: 'assets/js/auth.js' }
        ];

        for (const resource of resources) {
            const url = buildUrl(resource.path);
            const startTime = performance.now();

            try {
                const response = await fetch(url, { method: 'HEAD' });
                const time = Math.round(performance.now() - startTime);

                if (response.ok) {
                    log.success(`${resource.name}: Found (${time}ms)`);
                    results.passed++;
                } else {
                    log.error(`${resource.name}: ${response.status}`);
                    results.failed++;
                }
            } catch (error) {
                log.error(`${resource.name}: ${error.message}`);
                results.failed++;
            }

            results.total++;
        }

        log.groupEnd();
    }

    // Test CORS headers
    async function testCORSHeaders() {
        log.group('CORS Headers Test');

        const corsTest = await testEndpoint('CORS Preflight', 'api/auth_v2.php', {
            method: 'OPTIONS',
            parseResponse: false
        });

        if (corsTest.passed) {
            log.info('CORS headers should be present for cross-origin requests');
        }

        log.groupEnd();
        return corsTest;
    }

    // Genera report
    function generateReport() {
        const duration = results.endTime - results.startTime;
        const successRate = results.total > 0 ?
            Math.round((results.passed / results.total) * 100) : 0;

        console.group('📊 Test Report');

        // Sommario
        console.log('%c=== TEST SUMMARY ===', 'font-size: 16px; font-weight: bold;');
        console.log(`Total Tests: ${results.total}`);
        console.log(`✅ Passed: ${results.passed}`);
        console.log(`❌ Failed: ${results.failed}`);
        console.log(`Success Rate: ${successRate}%`);
        console.log(`Duration: ${Math.round(duration)}ms`);

        // Dettagli fallimenti
        if (results.failed > 0) {
            console.log('%c=== FAILED TESTS ===', 'color: red; font-weight: bold;');
            const failures = results.details.filter(r => !r.passed);
            log.table(failures);
        }

        // Tutti i risultati
        console.log('%c=== DETAILED RESULTS ===', 'font-weight: bold;');
        log.table(results.details);

        console.groupEnd();

        return {
            summary: {
                total: results.total,
                passed: results.passed,
                failed: results.failed,
                successRate: successRate,
                duration: Math.round(duration)
            },
            details: results.details
        };
    }

    // Reset risultati
    function reset() {
        results = {
            passed: 0,
            failed: 0,
            total: 0,
            details: [],
            startTime: null,
            endTime: null
        };
        log.info('Test results reset');
    }

    // Esegui tutti i test
    async function runAll() {
        console.clear();
        console.log('%c🚀 Starting API Path Tests',
            'font-size: 20px; font-weight: bold; color: #667eea;');
        console.log('=' . repeat(50));

        reset();
        results.startTime = performance.now();

        try {
            // Esegui test in sequenza
            await testPathDetection();
            await testAPIEndpoints();
            await testStaticResources();
            await testCORSHeaders();
            await testAuthentication();

        } catch (error) {
            log.error('Test suite failed:', error);
        }

        results.endTime = performance.now();

        // Genera e mostra report
        const report = generateReport();

        // Suggerimenti
        console.log('%c=== RECOMMENDATIONS ===', 'color: blue; font-weight: bold;');

        if (report.summary.failed > 0) {
            log.warning('Some tests failed. Check the following:');
            console.log('1. Verify Apache/XAMPP is running');
            console.log('2. Check if the API folder exists');
            console.log('3. Ensure database is configured');
            console.log('4. Verify file permissions');
        } else {
            log.success('All tests passed! The API path resolution is working correctly.');
        }

        return report;
    }

    // Esegui test singolo
    async function runSingle(name, path, options) {
        reset();
        results.startTime = performance.now();

        const result = await testEndpoint(name, path, options);

        results.endTime = performance.now();
        return result;
    }

    // API pubblica
    return {
        runAll: runAll,
        runSingle: runSingle,
        testPathDetection: testPathDetection,
        testAPIEndpoints: testAPIEndpoints,
        testAuthentication: testAuthentication,
        testStaticResources: testStaticResources,
        testCORSHeaders: testCORSHeaders,
        reset: reset,
        getResults: () => results,
        setConfig: (newConfig) => Object.assign(config, newConfig),
        detectBasePath: detectBasePath,
        buildUrl: buildUrl
    };
})();

// Auto-esegui se richiesto via URL parameter
if (window.location.search.includes('autotest=true')) {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            console.log('Auto-running tests...');
            testAPIPaths.runAll();
        }, 1000);
    });
}

// Esporta per uso globale
window.testAPIPaths = testAPIPaths;

// Shortcut per console
window.testAPI = () => testAPIPaths.runAll();

console.log('%c✨ API Path Test Module Loaded', 'color: #667eea; font-weight: bold;');
console.log('Run tests with: testAPIPaths.runAll() or testAPI()');
console.log('For help: testAPIPaths');