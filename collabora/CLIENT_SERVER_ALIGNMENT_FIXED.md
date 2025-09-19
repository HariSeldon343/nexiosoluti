# Client-Server Alignment Fix Documentation

## Date: 2025-01-18

## Problem
The client (auth_v2.js) was not properly distinguishing between 400 and 401 errors from the server (auth_simple.php), always showing "Credenziali non valide" even for missing field errors.

## Solution Implemented

### 1. Updated `/assets/js/auth_v2.js`

#### Key Changes:
- **Proper payload format**: Always sends `{"action":"login","email":"...","password":"..."}`
- **Correct headers**: Sets `Content-Type: application/json` and `Accept: application/json`
- **Credentials**: Uses `credentials: 'include'` for cookies/sessions
- **Status code handling**:
  - 200: Success → Redirect to dashboard
  - 400: Bad Request → Show field-specific errors
  - 401: Unauthorized → Show "Credenziali non valide"
  - 404: Fallback to auth_v2.php
  - 500: Server error

#### Error Display Logic:
```javascript
if (response.status === 400) {
    // Extract field errors
    if (data.error?.fields?.length > 0) {
        const fieldNames = data.error.fields.map(f => {
            if (f === 'email') return 'Email';
            if (f === 'password') return 'Password';
            return f;
        }).join(', ');
        errorMessage = `Campo mancante: ${fieldNames}`;
    }
} else if (response.status === 401) {
    errorMessage = 'Credenziali non valide';
}
```

### 2. Updated `/assets/js/error-handler.js`

#### Key Changes:
- **Special handling for 400 responses**:
  - Extracts `error.fields` array
  - Maps field names to Italian labels
  - Shows "Campo mancante: [fields]" message
- **401 always shows**: "Credenziali non valide"
- **Never shows "Errore di connessione"** for 400/401 (these are application errors, not network errors)

### 3. Updated `/assets/css/auth_v2.css`

#### Added Error Styles:
```css
/* Individual input error states */
.auth-form input.error {
    border-color: var(--error) !important;
    background: #FEF2F2;
}

.input-wrapper.has-error {
    animation: shake 0.3s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-2px); }
    20%, 40%, 60%, 80% { transform: translateX(2px); }
}
```

### 4. Created Test Files

#### `/test_auth_status.html`
- Interactive test page with 6 test scenarios
- Shows exact request/response for each test
- Visual status badges (200/400/401)
- Console output for debugging

#### `/test_client_server_alignment.php`
- PHP script to verify server-side logic
- Tests all error scenarios
- Validates response structure

## Server Response Structure (auth_simple.php)

### Success (200):
```json
{
  "success": true,
  "message": "Login effettuato con successo",
  "user": {...},
  "tenants": [...],
  "current_tenant_id": 1,
  "session_id": "..."
}
```

### Missing Fields (400):
```json
{
  "success": false,
  "error": {
    "code": "missing_fields",
    "message": "Required fields are missing",
    "fields": ["email", "password"]
  }
}
```

### Invalid Credentials (401):
```json
{
  "success": false,
  "error": {
    "code": "invalid_credentials",
    "message": "Email o password non corretti",
    "fields": []
  }
}
```

## Error Mapping

| Status | Error Code | Client Display |
|--------|------------|----------------|
| 400 | missing_fields | "Campo mancante: [field names]" |
| 400 | invalid_json | "Formato non valido" |
| 400 | empty_body | "Richiesta vuota" |
| 400 | no_data | "No input data received" |
| 401 | invalid_credentials | "Credenziali non valide" |
| 500 | database_error | "Database connection error" |

## Testing

### Test with cURL:

#### Valid login (200):
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_simple.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","email":"asamodeo@fortibyte.it","password":"Ricord@1991"}'
```

#### Missing email (400):
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_simple.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","password":"test"}'
```

#### Wrong password (401):
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_simple.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","email":"asamodeo@fortibyte.it","password":"wrong"}'
```

### Test in Browser:
1. Open `/test_auth_status.html`
2. Click each test button to see the response
3. Check console output for details

## Visual Feedback

### Field Errors (400):
- Input fields get red border
- Shake animation on error
- Light red background
- Field-specific error message

### Credential Errors (401):
- Both email and password fields marked as error
- Generic "Credenziali non valide" message
- No specific field indication

## Files Modified

1. `/assets/js/auth_v2.js` - Lines 95-225 (login form submission)
2. `/assets/js/error-handler.js` - Lines 186-256 (formatError method)
3. `/assets/css/auth_v2.css` - Lines 1540-1558 (error styles)

## Files Created

1. `/test_auth_status.html` - Interactive test page
2. `/test_client_server_alignment.php` - PHP validation script
3. `/CLIENT_SERVER_ALIGNMENT_FIXED.md` - This documentation

## Verification

The alignment is correct when:
- ✅ Missing fields show "Campo mancante: [field names]"
- ✅ Wrong credentials show "Credenziali non valide"
- ✅ Successful login redirects to dashboard
- ✅ Field errors highlight specific inputs
- ✅ Network errors are distinguished from application errors
- ✅ Error handler never shows "Errore di connessione" for 400/401

## Notes

- Server properly returns structured errors with `error.code` and `error.fields`
- Client properly parses and displays field-specific or generic errors
- Uses standard HTTP status codes (400 for validation, 401 for auth)
- All messages are in Italian as per system requirements
- Includes visual feedback with animations and color coding