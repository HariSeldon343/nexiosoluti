/**
 * Post-Login Configuration Module
 * Defines the configuration for post-login navigation behavior
 */

// Default landing page after successful login
const POST_LOGIN_DEFAULT = '/Nexiosolution/collabora/home_v2.php';

// Allowed redirect paths (whitelist for security)
// Only internal paths within the collabora application
const ALLOWED_REDIRECTS = [
    '/Nexiosolution/collabora/dashboard.php',
    '/Nexiosolution/collabora/home_v2.php',
    '/Nexiosolution/collabora/files.php',
    '/Nexiosolution/collabora/calendar.php',
    '/Nexiosolution/collabora/tasks.php',
    '/Nexiosolution/collabora/chat.php',
    '/Nexiosolution/collabora/settings.php',
    '/Nexiosolution/collabora/admin/',
    '/Nexiosolution/collabora/admin/index.php',
    '/Nexiosolution/collabora/admin/users.php',
    '/Nexiosolution/collabora/admin/tenants.php',
    '#dashboard',
    '#files',
    '#calendar',
    '#tasks',
    '#chat',
    '#settings'
];

// Application mode configuration
const ENABLE_SPA_MODE = false;  // false = MPA (Multi-Page Application) with full page redirects

// Redirect validation rules
const REDIRECT_RULES = {
    // Allow only relative paths starting with / or #
    allowedPrefixes: ['/', '#'],

    // Disallow external URLs (protocols)
    blockedProtocols: ['http://', 'https://', 'javascript:', 'data:', 'file:', 'ftp:', 'mailto:'],

    // Must contain this substring in the path
    requiredPathComponent: '/collabora/',

    // Disallow parent directory traversal
    blockedPatterns: ['../', '..\\', '%2e%2e', '\\\\'],

    // Login page (to prevent redirect loops)
    loginPages: [
        '/Nexiosolution/collabora/index.php',
        '/Nexiosolution/collabora/index_v2.php',
        '/Nexiosolution/collabora/login.php',
        '/Nexiosolution/collabora/'
    ],

    // Role-based default redirects
    roleDefaults: {
        'admin': '/Nexiosolution/collabora/admin/index.php',
        'special_user': '/Nexiosolution/collabora/home_v2.php',
        'standard_user': '/Nexiosolution/collabora/home_v2.php',
        'default': '/Nexiosolution/collabora/home_v2.php'
    }
};

// Debug configuration
const DEBUG_MODE = {
    enabled: true,  // Enable debug logging
    logNavigationDecisions: true,
    logValidationErrors: true,
    logRedirectTarget: true
};

// Timeout configuration (milliseconds)
const TIMEOUTS = {
    successMessage: 500,  // Time to show success message
    redirectDelay: 300    // Additional delay before redirect
};

// Export configuration
const PostLoginConfig = {
    POST_LOGIN_DEFAULT,
    ALLOWED_REDIRECTS,
    ENABLE_SPA_MODE,
    REDIRECT_RULES,
    DEBUG_MODE,
    TIMEOUTS
};

// Make configuration globally accessible
if (typeof window !== 'undefined') {
    window.PostLoginConfig = PostLoginConfig;
}

// Also export for ES6 modules
export default PostLoginConfig;