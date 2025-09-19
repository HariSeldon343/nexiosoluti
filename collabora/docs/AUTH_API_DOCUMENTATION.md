# Authentication API Documentation

## Overview
The Nexio Solution authentication API provides secure login functionality with proper HTTP status codes and detailed error messages.

## Base URL
```
http://localhost/Nexiosolution/collabora/api/
```

## Endpoints

### 1. Login - `/api/auth_simple.php`

#### Request
- **Method**: `POST`
- **Content-Type**: `application/json` or `application/x-www-form-urlencoded`

#### Required Fields
| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `action` | string | Must be "login" | `"login"` |
| `email` | string | User's email address | `"asamodeo@fortibyte.it"` |
| `password` | string | User's password | `"Ricord@1991"` |

#### Status Codes
| Code | Meaning | When Returned |
|------|---------|---------------|
| `200` | Success | Login successful |
| `400` | Bad Request | Missing fields, malformed JSON, invalid action |
| `401` | Unauthorized | Invalid email or password |
| `403` | Forbidden | Account inactive |
| `500` | Server Error | Database connection failed |

#### Response Examples

##### Success (200)
```json
{
    "success": true,
    "message": "Login effettuato con successo",
    "user": {
        "id": 1,
        "email": "asamodeo@fortibyte.it",
        "name": "Andrea Samodeo",
        "role": "admin",
        "is_admin": true
    },
    "tenants": [
        {
            "id": 1,
            "code": "DEFAULT",
            "name": "Default Tenant"
        }
    ],
    "current_tenant_id": 1,
    "session_id": "abc123...",
    "token": "abc123..."
}
```

##### Missing Fields (400)
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

##### Invalid JSON (400)
```json
{
    "success": false,
    "error": {
        "code": "invalid_json",
        "message": "Invalid JSON: Syntax error",
        "fields": []
    }
}
```

##### Invalid Credentials (401)
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

##### Database Error (500)
```json
{
    "success": false,
    "error": {
        "code": "database_error",
        "message": "Database connection error",
        "fields": []
    }
}
```

## Example Requests

### Using cURL with JSON
```bash
curl -X POST 'http://localhost/Nexiosolution/collabora/api/auth_simple.php' \
  -H 'Content-Type: application/json' \
  -d '{
    "action": "login",
    "email": "asamodeo@fortibyte.it",
    "password": "Ricord@1991"
  }'
```

### Using cURL with Form Data
```bash
curl -X POST 'http://localhost/Nexiosolution/collabora/api/auth_simple.php' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'action=login&email=asamodeo@fortibyte.it&password=Ricord@1991'
```

### Using JavaScript Fetch API
```javascript
// JSON format
fetch('http://localhost/Nexiosolution/collabora/api/auth_simple.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        action: 'login',
        email: 'asamodeo@fortibyte.it',
        password: 'Ricord@1991'
    })
})
.then(response => {
    if (!response.ok) {
        // Handle error based on status code
        if (response.status === 400) {
            console.error('Bad request - check your fields');
        } else if (response.status === 401) {
            console.error('Invalid credentials');
        }
    }
    return response.json();
})
.then(data => {
    if (data.success) {
        console.log('Login successful:', data.user);
    } else {
        console.error('Login failed:', data.error);
    }
});
```

### Using PHP
```php
$data = [
    'action' => 'login',
    'email' => 'asamodeo@fortibyte.it',
    'password' => 'Ricord@1991'
];

$ch = curl_init('http://localhost/Nexiosolution/collabora/api/auth_simple.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);
    echo "Login successful: " . $result['user']['name'];
} elseif ($httpCode === 401) {
    echo "Invalid credentials";
} elseif ($httpCode === 400) {
    $result = json_decode($response, true);
    echo "Missing fields: " . implode(', ', $result['error']['fields']);
}
```

## Debug Endpoint

### `/api/auth_debug.php`
Available only when `DEBUG_MODE` is enabled in configuration.

**GET Request:**
```bash
curl 'http://localhost/Nexiosolution/collabora/api/auth_debug.php'
```

Returns:
- API contract information
- Available users (email only)
- Database connection status
- Table structure
- Example requests
- Test credentials

## Error Handling Best Practices

1. **Always check HTTP status code first**
   - 2xx = Success
   - 4xx = Client error (fix your request)
   - 5xx = Server error (contact administrator)

2. **Parse error response for details**
   - `error.code`: Machine-readable error code
   - `error.message`: Human-readable message (can be shown to user)
   - `error.fields`: Array of fields with issues

3. **Logging**
   - Server logs sensitive operations without passwords
   - Check PHP error logs at `/xampp/php/logs/php_error_log`

## Security Notes

1. **HTTPS Required in Production**
   - Never send passwords over HTTP in production
   - Update `BASE_URL` in config to use HTTPS

2. **Rate Limiting**
   - API has built-in rate limiting (100 requests/minute)
   - Implement exponential backoff on client side

3. **Session Management**
   - Sessions expire after 2 hours of inactivity
   - Use the returned `session_id` or `token` for subsequent requests

## Test Credentials

| Email | Password | Role |
|-------|----------|------|
| `asamodeo@fortibyte.it` | `Ricord@1991` | Admin |

**Note**: Change these credentials in production!

## Troubleshooting

### Common Issues

1. **Getting 400 instead of 401**
   - Check if you're sending all required fields
   - Verify JSON is properly formatted
   - Use `auth_debug.php` to see expected format

2. **Getting 500 errors**
   - Database connection issues
   - Check if database `nexio_collabora_v2` exists
   - Verify MySQL is running

3. **CORS errors**
   - API allows all origins in development
   - Configure properly for production

### Validation Rules

- **Email**: Must be valid email format
- **Password**: At least 8 characters (configurable)
- **Action**: Must be one of: `login`, `logout`, `check`, `switch_tenant`, `test`