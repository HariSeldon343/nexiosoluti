# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Nexio Solution V2 is a multi-tenant collaborative file management system built with PHP 8.3+ vanilla (no frameworks) for XAMPP Windows. The system implements a sophisticated role-based authentication without requiring tenant codes at login.

## Key Architecture

### Authentication System (V2)
- **Session-based** authentication (NOT JWT) - uses PHP native sessions
- **No tenant code required** at login - system auto-detects tenant associations
- **Three user roles**:
  - `admin`: Full system control, no tenant restriction needed
  - `special_user`: Can switch between multiple tenants
  - `standard_user`: Single tenant access only
- **Default admin**: `asamodeo@fortibyte.it` / `Ricord@1991`
- Login flow: Email/Password → Role detection → Tenant assignment/selection → Dashboard

### Post-Login Redirect System (UPDATED 2025-01-19)
The system implements a priority-based, secure redirect system after successful authentication:

#### Redirect Priority Order:
1. **URL Parameter** (`?next=<url>`) - Highest priority, must pass security validation
2. **Server-Side Redirect** - Based on user role (admin → admin/index.php, others → home_v2.php)
3. **Configuration Default** - Fallback to `/Nexiosolution/collabora/home_v2.php`

#### Security Features:
- **URL Whitelist**: Only internal paths, hash navigation, and relative PHP files allowed
- **URL Blacklist**: Blocks external URLs, JavaScript injection, directory traversal
- **Deterministic Behavior**: Always redirects after login, never leaves user on login page

#### Configuration Files:
- **JavaScript Config**: `/assets/js/post-login-config.js` - Whitelist/blacklist patterns
- **JavaScript Handler**: `/assets/js/post-login-handler.js` - Redirect logic implementation
- **API Response**: `auth_simple.php` returns `redirect` field based on user role

#### Testing:
```bash
# Run comprehensive post-login tests
php test_login_complete.php

# Test with browser
Open: http://localhost/Nexiosolution/collabora/test_post_login.html
```

See `/docs/POST_LOGIN_FLOW.md` for complete documentation.

### Database Structure
- Database: `nexio_collabora_v2` (or `collabora_files` in some installations)
- Key tables: `users`, `tenants`, `user_tenant_associations`, `files`, `folders`
- User-tenant relationship: Many-to-many through `user_tenant_associations`
- All tables include `tenant_id` for data isolation (except admin operations)

### File Organization
```
/collabora/
├── /api/           # API endpoints (auth_v2.php, auth_simple.php, files.php, etc.)
├── /includes/      # Core classes (auth_v2.php, SimpleAuth.php, db.php, etc.)
├── /admin/         # Admin panel (users.php, tenants.php)
├── /components/    # UI components (sidebar.php, header.php)
├── /assets/        # CSS/JS files
│   └── /js/
│       ├── post-login-config.js    # Post-login redirect configuration
│       └── post-login-handler.js   # Post-login redirect logic
├── /docs/          # Documentation
│   └── POST_LOGIN_FLOW.md         # Complete post-login flow documentation
├── index_v2.php    # Main entry point (login/dashboard)
├── config_v2.php   # Main configuration file
├── test_login_complete.php  # Comprehensive login testing
└── LOGIN_REDIRECT_FIXED.md  # Implementation summary
```

### Critical Implementation Notes
- **Duplicate function prevention**: `getDbConnection()` exists only in `includes/db.php`, wrapped with `function_exists()` check
- **File inclusion**: Always use `require_once` to prevent duplicate includes
- **Namespace**: Uses `Collabora\Auth` namespace for auth classes
- **Autoloader**: Located at `/includes/autoload.php` - handles PSR-4 autoloading

## Common Commands

### System Setup & Testing
```bash
# Initial setup (Windows)
C:\xampp\htdocs\Nexiosolution\collabora\start_v2.bat

# Run system tests
php test_v2.php

# Initialize database
php init_database.php

# Test authentication
php test_auth.php
```

### Database Operations
```bash
# Create database and tables
mysql -u root < database/schema_v2.sql

# Backup database
C:\xampp\htdocs\Nexiosolution\collabora\backup.bat
```

### Development Testing
```bash
# Test specific API endpoint (WORKING AS OF 2025-01-18)
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_simple.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","email":"asamodeo@fortibyte.it","password":"Ricord@1991"}'

# Test auth_v2.php endpoint (WORKING AS OF 2025-01-18)
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_v2.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","email":"asamodeo@fortibyte.it","password":"Ricord@1991"}'

# Run comprehensive test suite
php test_auth_final.php

# Or use the shell test script
bash test_curl_auth.sh

# Check PHP syntax
php -l index_v2.php

# View PHP configuration
php -i | grep -E "mysqli|pdo_mysql|json|mbstring|openssl"
```

## Common Issues & Solutions

### Authentication Errors (UPDATED 2025-01-18)
1. **"Class not found"**: Check autoloader at `/includes/autoload.php` and namespace usage
2. **"Cannot redeclare function"**: Ensure `require_once` usage and check `db.php` for `function_exists()` wrapper
3. **404 on API calls**: ✅ FIXED - Both `/api/auth_v2.php` and `/api/auth_simple.php` now fully operational
4. **400 Bad Request**: ✅ FIXED - Enhanced error messages with specific details about missing/invalid fields
5. **Login fails**: Check database exists (`nexio_collabora_v2`), user table has admin user, password hash is correct

### Recently Fixed Issues (January 2025)
- **auth_v2.php returning 404**: Created complete implementation with AuthAPIV2 class
- **auth_simple.php returning 400**: Added flexible JSON/form-encoded support and detailed error messages
- **Generic error messages**: Implemented specific error reporting with debug information
- **JSON format issues**: Added support for multiple content types and fallback parsing

### Database Connection
- Config file: `config_v2.php` - contains DB credentials (usually root with no password for XAMPP)
- Connection function: `getDbConnection()` in `includes/db.php`
- Default database name: `nexio_collabora_v2` (sometimes `collabora_files` in older setups)

## UI Design Requirements
- **Sidebar color**: Grigio antracite (#111827) - strictly maintained
- **Icons**: Heroicons inline SVG only - no external icon libraries
- **Layout**: CSS Grid/Flexbox - no CSS frameworks
- **Responsive**: Mobile-first with collapsible sidebar
- **Dark mode**: Supported via CSS variables

## Security Considerations
- All database queries use PDO prepared statements
- CSRF tokens required for all write operations
- Passwords hashed with Argon2id or bcrypt
- Session security: HTTPOnly, SameSite cookies
- File uploads validated for MIME type and stored outside document root with SHA256 deduplication

## API Path Resolution System

### Dynamic Path Detection
The system now implements automatic path detection for API endpoints, eliminating hardcoded paths:

1. **Priority Order**:
   - Environment variable: `COLLABORA_BASE_URL`
   - Configuration constant: `BASE_URL` in config_v2.php
   - Auto-detection: Calculates from current script location

2. **Path Resolution Logic**:
   ```javascript
   // Frontend (JavaScript)
   const scriptPath = window.location.pathname;
   const pathParts = scriptPath.split('/');
   const collaboraIndex = pathParts.indexOf('collabora');
   const basePath = pathParts.slice(0, collaboraIndex + 1).join('/');
   const apiUrl = basePath + '/api';
   ```

3. **API Module Usage**:
   ```javascript
   // Import API module
   import { API } from '/assets/js/api.js';

   // Use API methods
   await API.auth.login(email, password);
   await API.users.getAll();
   await API.files.upload(file);
   ```

### Troubleshooting Path Issues

1. **API calls return 404**:
   - Check browser console for actual URL being called
   - Verify `/api/` folder exists in collabora directory
   - Ensure .htaccess isn't blocking API requests

2. **Wrong base URL detected**:
   - Set `BASE_URL` constant in config_v2.php
   - Or set environment variable `COLLABORA_BASE_URL`

3. **Subfolder installation issues**:
   - System auto-detects subfolder depth
   - Works with any path: `/collabora/`, `/app/collabora/`, `/Nexiosolution/collabora/`

4. **Testing path resolution**:
   - Use `/test_api_paths.php` for server-side testing
   - Use `/test_api_resolution.html` for browser testing
   - Run `window.testAPIFromConsole()` in browser console

## API Response Format
All API endpoints should return JSON:
```json
{
  "success": true/false,
  "message": "Human readable message",
  "data": {...},
  "error": "Error code if applicable"
}
```

## File Upload Structure
Files stored in: `/uploads/{tenant_code}/{year}/{month}/`
with SHA256 hash for deduplication across tenants.

## Testing Approach
Always test with three scenarios:
1. Admin login (full access)
2. Special user (multi-tenant switching)
3. Standard user (single tenant)

Default test credentials are provided in the system documentation.