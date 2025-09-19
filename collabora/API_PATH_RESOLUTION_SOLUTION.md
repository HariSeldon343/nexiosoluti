# API Path Resolution Solution - Complete Implementation

## Problem Solved
The application was using hardcoded API paths (`/collabora/api/`) which caused 404 errors when installed in different directories (e.g., `/Nexiosolution/collabora/`). API calls were failing because they didn't account for the actual installation path.

## Solution Implemented

### 1. Created Centralized API Configuration Module
**File:** `/assets/js/api-config.js`

This module provides:
- **Dynamic base URL detection** from current page location
- **Automatic path resolution** that works in any subfolder
- **Unified API request methods** with error handling
- **CSRF token management**
- **Upload progress tracking**

#### Key Features:
```javascript
// Automatically detects base path from URL
// Works with: /collabora/, /Nexiosolution/collabora/, /myapp/collabora/, etc.
const basePath = APIConfig.detectBasePath();
const apiUrl = APIConfig.buildApiUrl('auth_simple.php');

// Unified API calls
await APIConfig.post('users.php', data);
await APIConfig.get('logs.php', params);
```

### 2. Updated JavaScript Files

All JavaScript files now use the centralized APIConfig module:

#### Updated Files:
1. **`/assets/js/auth_v2.js`**
   - Login/logout API calls
   - Tenant switching
   - Notification management

2. **`/assets/js/admin.js`**
   - User management API calls
   - Tenant management API calls
   - Backup operations
   - Activity logs

3. **`/assets/js/filemanager.js`**
   - File upload/download
   - File operations
   - Preview/thumbnail URLs

### 3. Updated HTML/PHP Files

Script loading order updated to load api-config.js first:

#### Updated Files:
1. **`/index_v2.php`**
2. **`/admin/index.php`**
3. **`/admin/users.php`**
4. **`/admin/tenants.php`**

```html
<!-- API Configuration (must load first) -->
<script src="assets/js/api-config.js"></script>
<!-- Other scripts follow -->
<script src="assets/js/auth_v2.js"></script>
```

### 4. Added Test Endpoints

Added test actions to API files for verification:
- `auth_simple.php` - Added 'test' action
- `auth_v2.php` - Added 'test' action

### 5. Created Test Page

**File:** `/test_api_config.html`

A comprehensive test page that:
- Shows detected paths and API URLs
- Tests all API endpoints
- Displays debug information
- Verifies configuration is working

## How It Works

### Priority Order for Base URL Resolution:
1. **Global configuration** (`window.API_CONFIG.baseUrl`)
2. **Data attribute** from script tag
3. **Automatic detection** from current page URL

### Path Detection Algorithm:
```javascript
function detectBasePath() {
    const pathname = window.location.pathname;

    // Look for '/collabora/' in the current path
    const collaboraIndex = pathname.indexOf('/collabora/');

    if (collaboraIndex !== -1) {
        // Extract everything up to and including '/collabora'
        return pathname.substring(0, collaboraIndex + '/collabora'.length);
    }

    // Fallback methods...
}
```

## Benefits

1. **Zero Configuration Required** - Works automatically regardless of installation location
2. **No Code Changes on Move** - Moving the folder requires NO modifications
3. **Backwards Compatible** - Falls back gracefully if APIConfig not available
4. **Centralized Error Handling** - Consistent error management across all API calls
5. **Better Debugging** - Clear error messages with endpoint information
6. **CSRF Protection** - Automatic token inclusion in all requests

## Testing the Solution

1. **Open the test page:**
   ```
   http://localhost/Nexiosolution/collabora/test_api_config.html
   ```

2. **Verify detected paths:**
   - Check that Base Path shows: `/Nexiosolution/collabora`
   - Check that API Base URL shows: `/Nexiosolution/collabora/api`

3. **Test API endpoints:**
   - Click test buttons to verify each endpoint
   - All should return success or proper error messages

## API Endpoints Available

All endpoints now work with dynamic path resolution:
- `auth_simple.php` - Simple authentication
- `auth_v2.php` - Advanced authentication v2
- `files.php` - File management
- `folders.php` - Folder operations
- `users.php` - User management
- `tenants.php` - Tenant management
- `webdav.php` - WebDAV operations
- `webhooks.php` - Webhook management
- `notifications.php` - Notifications
- `backup.php` - Backup operations
- `logs.php` - Activity logs

## Migration Notes

If you have custom JavaScript files making API calls, update them to use APIConfig:

### Before:
```javascript
const response = await fetch('/collabora/api/users.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
});
```

### After:
```javascript
const response = await APIConfig.post('users.php', data);
```

## Troubleshooting

1. **APIConfig not defined:**
   - Ensure `api-config.js` is loaded before other scripts
   - Check browser console for loading errors

2. **Wrong path detected:**
   - Check `test_api_config.html` to see what's being detected
   - Can override with: `APIConfig.initialize({ baseUrl: '/custom/path/api' })`

3. **404 errors persist:**
   - Verify API files exist in `/api/` directory
   - Check Apache/XAMPP error logs
   - Ensure `.htaccess` isn't blocking requests

## Summary

This solution completely resolves the API path resolution issue. The application now:
- ✅ Works in any installation directory
- ✅ Requires zero configuration
- ✅ Maintains backwards compatibility
- ✅ Provides better error handling
- ✅ Is fully portable

The system is now truly "drop-in" ready - you can move the `/collabora/` folder anywhere within your web root and it will continue to work without any code modifications.