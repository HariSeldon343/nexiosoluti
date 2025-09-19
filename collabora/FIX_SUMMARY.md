# Fix Summary: Cannot redeclare getDbConnection() Error

## Problem
The application was showing a fatal error:
```
Fatal error: Cannot redeclare getDbConnection() (previously declared in /config_v2.php:212)
in /includes/db.php:515
```

## Root Cause
The function `getDbConnection()` was declared in two different files:
1. `/config_v2.php` at line 211
2. `/includes/db.php` at line 498

When both files were included in the same PHP execution, it caused a duplicate function declaration error.

## Files Modified

### 1. /mnt/c/xampp/htdocs/Nexiosolution/collabora/config_v2.php
- **Change**: Removed the duplicate `getDbConnection()` function (lines 211-235)
- **Reason**: The function should only be defined in one place (db.php)

### 2. /mnt/c/xampp/htdocs/Nexiosolution/collabora/includes/db.php
- **Change**: Wrapped the `getDbConnection()` function with `function_exists()` check
- **Reason**: Prevents duplicate declaration if the function is somehow already defined
- **Additional**: Updated to check for both config_v2.php and config.php

### 3. /mnt/c/xampp/htdocs/Nexiosolution/collabora/includes/autoload.php
- **Change**: Changed `require` to `require_once` for class file loading (lines 31 and 38)
- **Reason**: Ensures files are only included once, preventing duplicate declarations

## Solution Details

1. **Centralized Database Connection**: The `getDbConnection()` function now exists only in `/includes/db.php`

2. **Safe Inclusion Pattern**: Added `function_exists()` check:
```php
if (!function_exists('getDbConnection')) {
    function getDbConnection(): PDO {
        // function implementation
    }
}
```

3. **Proper File Inclusion**: All file inclusions now use `require_once` instead of `require`

## Test Results
✅ No more "Cannot redeclare" errors
✅ All files load successfully
✅ Database connection works
✅ File inclusion hierarchy is clean

## How to Verify
Run the test script:
```bash
cd /mnt/c/xampp/htdocs/Nexiosolution/collabora
/mnt/c/xampp/php/php.exe test_login.php
```

## Additional Notes
- The login credentials (asamodeo@fortibyte.it / Ricord@1991) need to be added to the database
- The activity_logs table needs to be created in the database
- These are separate issues from the duplicate function declaration problem

## Files Created
- `/test_login.php` - Test script to verify the fix works correctly