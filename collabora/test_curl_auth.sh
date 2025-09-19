#!/bin/bash

################################################################################
# Test Script per Sistema Autenticazione Nexio Solution
# Version: 1.0.0
# Date: 2025-01-18
#
# Uso: bash test_curl_auth.sh [endpoint]
# Esempi:
#   bash test_curl_auth.sh                # Testa entrambi gli endpoint
#   bash test_curl_auth.sh simple         # Testa solo auth_simple.php
#   bash test_curl_auth.sh v2             # Testa solo auth_v2.php
################################################################################

# Configurazione
BASE_URL="http://localhost/Nexiosolution/collabora"
AUTH_SIMPLE="${BASE_URL}/api/auth_simple.php"
AUTH_V2="${BASE_URL}/api/auth_v2.php"

# Credenziali
VALID_EMAIL="asamodeo@fortibyte.it"
VALID_PASSWORD="Ricord@1991"
INVALID_EMAIL="test@example.com"
INVALID_PASSWORD="wrongpassword"

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Contatori
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Funzione per stampare header
print_header() {
    echo ""
    echo "================================================================================"
    echo "$1"
    echo "================================================================================"
}

# Funzione per stampare risultato test
print_result() {
    local test_name=$1
    local result=$2
    local response=$3

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    if [ "$result" == "PASS" ]; then
        PASSED_TESTS=$((PASSED_TESTS + 1))
        echo -e "${GREEN}✓${NC} $test_name: ${GREEN}PASSED${NC}"
    else
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo -e "${RED}✗${NC} $test_name: ${RED}FAILED${NC}"
    fi

    if [ ! -z "$response" ]; then
        echo "  Response: $response"
    fi
}

# Funzione per test con cURL
test_endpoint() {
    local endpoint=$1
    local data=$2
    local expected_success=$3
    local test_name=$4

    echo ""
    echo -e "${BLUE}Testing:${NC} $test_name"
    echo "Endpoint: $endpoint"
    echo "Payload: $data"

    response=$(curl -s -w "\n%{http_code}" -X POST "$endpoint" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d "$data" 2>/dev/null)

    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')

    echo "HTTP Code: $http_code"
    echo "Response Body: $body"

    # Verifica il risultato
    if [ "$expected_success" == "true" ]; then
        if [ "$http_code" == "200" ] && echo "$body" | grep -q '"success":true'; then
            print_result "$test_name" "PASS" ""
        else
            print_result "$test_name" "FAIL" "Expected success but got error"
        fi
    else
        if [ "$http_code" != "200" ] || echo "$body" | grep -q '"success":false'; then
            print_result "$test_name" "PASS" ""
        else
            print_result "$test_name" "FAIL" "Expected error but got success"
        fi
    fi
}

# Funzione per testare auth_simple.php
test_auth_simple() {
    print_header "TESTING AUTH_SIMPLE.PHP"

    # Test 1: Login con credenziali valide
    test_endpoint "$AUTH_SIMPLE" \
        '{"action":"login","email":"'$VALID_EMAIL'","password":"'$VALID_PASSWORD'"}' \
        "true" \
        "auth_simple.php - Login valido"

    # Test 2: Login con credenziali invalide
    test_endpoint "$AUTH_SIMPLE" \
        '{"action":"login","email":"'$INVALID_EMAIL'","password":"'$INVALID_PASSWORD'"}' \
        "false" \
        "auth_simple.php - Login invalido"

    # Test 3: Action mancante
    test_endpoint "$AUTH_SIMPLE" \
        '{"email":"'$VALID_EMAIL'","password":"'$VALID_PASSWORD'"}' \
        "false" \
        "auth_simple.php - Action mancante"

    # Test 4: JSON malformato
    test_endpoint "$AUTH_SIMPLE" \
        '{invalid json}' \
        "false" \
        "auth_simple.php - JSON malformato"

    # Test 5: Test endpoint
    test_endpoint "$AUTH_SIMPLE" \
        '{"action":"test"}' \
        "true" \
        "auth_simple.php - Test endpoint"

    # Test 6: Check autenticazione
    test_endpoint "$AUTH_SIMPLE" \
        '{"action":"check"}' \
        "true" \
        "auth_simple.php - Check autenticazione"

    # Test 7: Action non valida
    test_endpoint "$AUTH_SIMPLE" \
        '{"action":"invalid_action"}' \
        "false" \
        "auth_simple.php - Action invalida"
}

# Funzione per testare auth_v2.php
test_auth_v2() {
    print_header "TESTING AUTH_V2.PHP"

    # Test 1: Login con credenziali valide
    test_endpoint "$AUTH_V2" \
        '{"action":"login","email":"'$VALID_EMAIL'","password":"'$VALID_PASSWORD'"}' \
        "true" \
        "auth_v2.php - Login valido"

    # Test 2: Login con credenziali invalide
    test_endpoint "$AUTH_V2" \
        '{"action":"login","email":"'$INVALID_EMAIL'","password":"'$INVALID_PASSWORD'"}' \
        "false" \
        "auth_v2.php - Login invalido"

    # Test 3: Email mancante
    test_endpoint "$AUTH_V2" \
        '{"action":"login","password":"'$VALID_PASSWORD'"}' \
        "false" \
        "auth_v2.php - Email mancante"

    # Test 4: Password mancante
    test_endpoint "$AUTH_V2" \
        '{"action":"login","email":"'$VALID_EMAIL'"}' \
        "false" \
        "auth_v2.php - Password mancante"

    # Test 5: Test endpoint
    test_endpoint "$AUTH_V2" \
        '{"action":"test"}' \
        "true" \
        "auth_v2.php - Test endpoint"

    # Test 6: GET request su /me (senza autenticazione)
    echo ""
    echo -e "${BLUE}Testing:${NC} auth_v2.php - GET /me senza auth"
    response=$(curl -s -w "\n%{http_code}" -X GET "$AUTH_V2/me" 2>/dev/null)
    http_code=$(echo "$response" | tail -n1)
    if [ "$http_code" == "401" ]; then
        print_result "auth_v2.php - GET /me senza auth" "PASS" ""
    else
        print_result "auth_v2.php - GET /me senza auth" "FAIL" "Expected 401 but got $http_code"
    fi
}

# Funzione per test avanzati con sessione
test_session_flow() {
    print_header "TESTING SESSION FLOW"

    echo -e "${YELLOW}Nota: I test di sessione richiedono cookie support${NC}"

    # Login e salva cookie
    echo ""
    echo "1. Effettuo login e salvo la sessione..."

    cookie_jar="/tmp/nexio_cookies.txt"

    response=$(curl -s -c "$cookie_jar" -X POST "$AUTH_SIMPLE" \
        -H "Content-Type: application/json" \
        -d '{"action":"login","email":"'$VALID_EMAIL'","password":"'$VALID_PASSWORD'"}' 2>/dev/null)

    if echo "$response" | grep -q '"success":true'; then
        echo -e "${GREEN}Login effettuato con successo${NC}"
        session_id=$(echo "$response" | grep -o '"session_id":"[^"]*"' | cut -d'"' -f4)
        echo "Session ID: $session_id"

        # Test check con sessione
        echo ""
        echo "2. Verifico stato autenticazione con sessione..."

        check_response=$(curl -s -b "$cookie_jar" -X POST "$AUTH_SIMPLE" \
            -H "Content-Type: application/json" \
            -d '{"action":"check"}' 2>/dev/null)

        if echo "$check_response" | grep -q '"authenticated":true'; then
            print_result "Check con sessione valida" "PASS" ""
        else
            print_result "Check con sessione valida" "FAIL" ""
        fi

        # Logout
        echo ""
        echo "3. Effettuo logout..."

        logout_response=$(curl -s -b "$cookie_jar" -X POST "$AUTH_SIMPLE" \
            -H "Content-Type: application/json" \
            -d '{"action":"logout"}' 2>/dev/null)

        if echo "$logout_response" | grep -q '"success":true'; then
            print_result "Logout" "PASS" ""
        else
            print_result "Logout" "FAIL" ""
        fi

        # Cleanup
        rm -f "$cookie_jar"

    else
        echo -e "${RED}Login fallito, skip test di sessione${NC}"
    fi
}

# Funzione per stampare il summary
print_summary() {
    print_header "TEST SUMMARY"

    echo "Total Tests: $TOTAL_TESTS"
    echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
    echo -e "Failed: ${RED}$FAILED_TESTS${NC}"

    if [ $FAILED_TESTS -eq 0 ]; then
        echo -e "\n${GREEN}✓ TUTTI I TEST SONO PASSATI!${NC}"
    else
        echo -e "\n${RED}✗ ALCUNI TEST SONO FALLITI${NC}"
    fi

    success_rate=$((PASSED_TESTS * 100 / TOTAL_TESTS))
    echo "Success Rate: ${success_rate}%"
}

# Main execution
clear
echo "################################################################################"
echo "#                    NEXIO SOLUTION - AUTH SYSTEM TEST SUITE                  #"
echo "################################################################################"
echo "Date: $(date)"
echo "Base URL: $BASE_URL"
echo ""

# Verifica che il server sia raggiungibile
echo -n "Verifico connessione al server... "
if curl -s --head "$BASE_URL" > /dev/null; then
    echo -e "${GREEN}OK${NC}"
else
    echo -e "${RED}FAILED${NC}"
    echo "Impossibile connettersi a $BASE_URL"
    echo "Assicurati che XAMPP sia avviato e il progetto sia accessibile."
    exit 1
fi

# Parse arguments
case "${1:-all}" in
    simple)
        test_auth_simple
        ;;
    v2)
        test_auth_v2
        ;;
    session)
        test_session_flow
        ;;
    all|*)
        test_auth_simple
        test_auth_v2
        test_session_flow
        ;;
esac

# Print summary
print_summary

echo ""
echo "Test completato: $(date)"
echo "################################################################################"

# Exit with appropriate code
if [ $FAILED_TESTS -gt 0 ]; then
    exit 1
else
    exit 0
fi