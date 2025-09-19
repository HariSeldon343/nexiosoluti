# Authentication System V2 - Documentation

## Overview
This is a complete role-based authentication system with multi-tenant support for PHP 8.3+. The system provides secure session-based authentication without JWT, with three distinct user roles and comprehensive tenant management.

## Features

### Core Features
- ✅ **Session-based authentication** (no JWT required)
- ✅ **Three user roles**: admin, special_user, standard_user
- ✅ **Multi-tenant support** with tenant switching
- ✅ **Role-based permissions**
- ✅ **Activity logging**
- ✅ **CSRF protection**
- ✅ **Password security** (Argon2id hashing)
- ✅ **Session management** with automatic cleanup
- ✅ **Prepared statements** for all queries

### User Roles

#### 1. Admin (`admin`)
- Full system access
- Can manage all users and tenants
- Access without tenant association
- System-wide privileges

#### 2. Special User (`special_user`)
- Can switch between multiple tenants
- Access to assigned tenants only
- Enhanced permissions within tenants

#### 3. Standard User (`standard_user`)
- Single tenant access
- Basic permissions
- Limited to assigned tenant

## Installation

### 1. Database Setup

Run the migration script to create the necessary tables:

```bash
# Navigate to the project directory
cd /mnt/c/xampp/htdocs/Nexiosolution/collabora

# Run the setup script
php setup_auth_v2.php
```

Or manually execute the SQL migration:
```sql
mysql -u root -p collabora_files < database/migration_v2_auth.sql
```

### 2. Default Admin Credentials

After setup, the default admin account is:
- **Email**: asamodeo@fortibyte.it
- **Password**: Ricord@1991

## File Structure

```
/collabora/
├── /includes/
│   ├── auth_v2.php           # Core authentication system
│   ├── UserManager.php       # User management class
│   ├── TenantManager_v2.php  # Tenant management class
│   └── db.php                # Database connection
├── /api/
│   ├── auth_v2.php          # Authentication endpoints
│   ├── users.php            # User management API
│   └── tenants.php          # Tenant management API
├── /database/
│   └── migration_v2_auth.sql # Database migration
└── setup_auth_v2.php         # Setup script
```

## API Endpoints

### Authentication (`/api/auth_v2/`)

#### Login
```http
POST /api/auth_v2/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}
```

#### Logout
```http
POST /api/auth_v2/logout
X-CSRF-Token: {token}
```

#### Get Current User
```http
GET /api/auth_v2/me
```

#### Get User Tenants
```http
GET /api/auth_v2/tenants
```

#### Switch Tenant
```http
POST /api/auth_v2/switch-tenant
Content-Type: application/json
X-CSRF-Token: {token}

{
  "tenant_id": 2
}
```

#### Get CSRF Token
```http
GET /api/auth_v2/csrf-token
```

### User Management (`/api/users/`) - Admin Only

#### List Users
```http
GET /api/users?page=1&limit=20&search=john&role=admin&status=active
```

#### Get User
```http
GET /api/users/{id}
```

#### Create User
```http
POST /api/users
Content-Type: application/json
X-CSRF-Token: {token}

{
  "email": "newuser@example.com",
  "password": "SecurePass@123",
  "first_name": "John",
  "last_name": "Doe",
  "role": "standard_user",
  "tenant_associations": [
    {
      "tenant_id": 1,
      "role_in_tenant": "user",
      "is_primary": true
    }
  ]
}
```

#### Update User
```http
PUT /api/users/{id}
Content-Type: application/json
X-CSRF-Token: {token}

{
  "first_name": "Jane",
  "role": "special_user"
}
```

#### Delete User
```http
DELETE /api/users/{id}
X-CSRF-Token: {token}
```

#### Associate User to Tenant
```http
POST /api/users/{id}/tenants
Content-Type: application/json
X-CSRF-Token: {token}

{
  "tenant_id": 2,
  "role_in_tenant": "manager"
}
```

### Tenant Management (`/api/tenants/`)

#### List Tenants
```http
GET /api/tenants?page=1&limit=20&search=acme
```

#### Get Available Tenants
```http
GET /api/tenants/available
```

#### Get Current Tenant
```http
GET /api/tenants/current
```

#### Get Tenant
```http
GET /api/tenants/{id}
```

#### Create Tenant (Admin Only)
```http
POST /api/tenants
Content-Type: application/json
X-CSRF-Token: {token}

{
  "name": "Acme Corporation",
  "domain": "acme.example.com",
  "storage_quota_gb": 100,
  "max_users": 50,
  "subscription_tier": "professional"
}
```

#### Update Tenant
```http
PUT /api/tenants/{id}
Content-Type: application/json
X-CSRF-Token: {token}

{
  "name": "Acme Corp",
  "storage_quota_gb": 200
}
```

#### Delete Tenant (Admin Only)
```http
DELETE /api/tenants/{id}
X-CSRF-Token: {token}
```

## PHP Usage Examples

### Basic Authentication

```php
<?php
use Collabora\Auth\AuthenticationV2;

// Initialize auth
$auth = new AuthenticationV2();

// Login
try {
    $result = $auth->login('user@example.com', 'password');
    echo "Login successful!";
    print_r($result);
} catch (Exception $e) {
    echo "Login failed: " . $e->getMessage();
}

// Check if authenticated
if ($auth->isAuthenticated()) {
    $user = $auth->getCurrentUser();
    echo "Welcome, " . $user['first_name'];
}

// Check permissions
if ($auth->hasPermission('users.create')) {
    echo "Can create users";
}

// Check if admin
if ($auth->isAdmin()) {
    echo "User is admin";
}

// Switch tenant (for special users)
try {
    $auth->switchTenant(2);
    echo "Switched to tenant 2";
} catch (Exception $e) {
    echo "Cannot switch: " . $e->getMessage();
}

// Logout
$auth->logout();
```

### User Management

```php
<?php
use Collabora\Users\UserManager;

$userManager = new UserManager();

// Create user
$userData = [
    'email' => 'john.doe@example.com',
    'password' => 'SecurePass@123',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'role' => 'standard_user',
    'tenant_id' => 1
];

try {
    $user = $userManager->createUser($userData);
    echo "User created: " . $user['id'];
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Search users
$results = $userManager->searchUsers(
    ['search' => 'john', 'role' => 'admin'],
    1,  // page
    20  // limit
);

foreach ($results['users'] as $user) {
    echo $user['email'] . " - " . $user['role'] . "\n";
}

// Update user
$userManager->updateUser($userId, [
    'role' => 'special_user',
    'status' => 'active'
]);

// Delete user (soft delete)
$userManager->deleteUser($userId);
```

### Tenant Management

```php
<?php
use Collabora\Tenants\TenantManagerV2;

$tenantManager = new TenantManagerV2();

// Create tenant
$tenantData = [
    'name' => 'New Company',
    'storage_quota_gb' => 50,
    'subscription_tier' => 'starter'
];

$tenant = $tenantManager->createTenant($tenantData);

// Get user's available tenants
$tenants = $tenantManager->getUserAvailableTenants();

// Switch to tenant
$tenantManager->switchToTenant($tenantId);

// Update tenant
$tenantManager->updateTenant($tenantId, [
    'name' => 'Updated Name',
    'storage_quota_gb' => 100
]);
```

## Security Features

### Password Requirements
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character (@$!%*?&)

### Session Security
- Secure session cookies (httponly, samesite=Strict)
- Session regeneration on login
- Automatic session cleanup (24 hours)
- IP address tracking

### CSRF Protection
All state-changing operations require a valid CSRF token:
```javascript
// Get CSRF token
fetch('/api/auth_v2/csrf-token')
  .then(res => res.json())
  .then(data => {
    const csrfToken = data.data.csrf_token;

    // Use in subsequent requests
    fetch('/api/users', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify(userData)
    });
  });
```

## Database Schema

### Main Tables
- `users` - User accounts
- `tenants` - Tenant organizations
- `user_tenant_associations` - User-tenant mappings
- `user_sessions` - Active sessions
- `activity_logs` - Audit trail
- `permission_sets` - Role permissions

## Testing

### 1. Test Authentication
```bash
# Login
curl -X POST http://localhost/collabora/api/auth_v2/login \
  -H "Content-Type: application/json" \
  -d '{"email":"asamodeo@fortibyte.it","password":"Ricord@1991"}'

# Get current user
curl http://localhost/collabora/api/auth_v2/me \
  -H "Cookie: COLLABORA_SESSID={session_id}"
```

### 2. Test User Management
```bash
# List users (requires admin)
curl http://localhost/collabora/api/users \
  -H "Cookie: COLLABORA_SESSID={session_id}"

# Create user
curl -X POST http://localhost/collabora/api/users \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: {csrf_token}" \
  -H "Cookie: COLLABORA_SESSID={session_id}" \
  -d '{
    "email": "test@example.com",
    "password": "Test@1234",
    "first_name": "Test",
    "last_name": "User",
    "role": "standard_user"
  }'
```

### 3. Test Tenant Management
```bash
# Get available tenants
curl http://localhost/collabora/api/tenants/available \
  -H "Cookie: COLLABORA_SESSID={session_id}"

# Switch tenant
curl -X POST http://localhost/collabora/api/tenants/switch \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: {csrf_token}" \
  -H "Cookie: COLLABORA_SESSID={session_id}" \
  -d '{"tenant_id": 2}'
```

## Troubleshooting

### Common Issues

1. **"Database connection failed"**
   - Check database credentials in `config.php`
   - Ensure MySQL is running
   - Verify database exists

2. **"Token CSRF non valido"**
   - Ensure you're including the CSRF token in requests
   - Token expires after 1 hour - get a new one

3. **"Non hai i permessi"**
   - Check user role and permissions
   - Admin-only operations require admin role

4. **Session not persisting**
   - Check session.save_path is writable
   - Verify cookies are enabled
   - Check for HTTPS if secure cookies are set

## Production Checklist

- [ ] Change default admin password
- [ ] Enable HTTPS for secure cookies
- [ ] Configure proper session storage
- [ ] Set up log rotation
- [ ] Configure error reporting (disable in production)
- [ ] Set up database backups
- [ ] Configure rate limiting
- [ ] Review and adjust PHP settings
- [ ] Set up monitoring for failed login attempts
- [ ] Configure email for password resets

## License
Proprietary - Nexiosolution

## Support
For support, contact: support@nexiosolution.com