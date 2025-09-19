/**
 * Post-Login Handler Module
 * Handles secure navigation after successful authentication
 */

// Import configuration (will be loaded via script tag)
const Config = window.PostLoginConfig || {
    POST_LOGIN_DEFAULT: '/Nexiosolution/collabora/home_v2.php',
    REDIRECT_RULES: {
        blockedProtocols: ['http://', 'https://', 'javascript:', 'data:', 'file:', 'ftp:', 'mailto:'],
        blockedPatterns: ['../', '..\\', '%2e%2e', '\\\\'],
        requiredPathComponent: '/collabora/',
        loginPages: ['/Nexiosolution/collabora/index.php', '/Nexiosolution/collabora/index_v2.php']
    },
    DEBUG_MODE: { enabled: true }
};

/**
 * Log debug information
 */
function debugLog(message, data = null) {
    if (Config.DEBUG_MODE && Config.DEBUG_MODE.enabled) {
        const timestamp = new Date().toISOString();
        console.log(`[PostLogin ${timestamp}] ${message}`, data || '');
    }
}

/**
 * Validate a redirect target for security
 * @param {string} target - The target URL to validate
 * @returns {object} - { valid: boolean, reason: string }
 */
function validateRedirectTarget(target) {
    debugLog('Validating redirect target', target);

    // Check if target is empty
    if (!target || target.trim() === '') {
        return { valid: false, reason: 'Empty redirect target' };
    }

    const normalizedTarget = target.trim().toLowerCase();

    // Check for blocked protocols (prevent external URLs)
    for (const protocol of Config.REDIRECT_RULES.blockedProtocols) {
        if (normalizedTarget.startsWith(protocol)) {
            debugLog('Blocked protocol detected', protocol);
            return { valid: false, reason: `Blocked protocol: ${protocol}` };
        }
    }

    // Check for directory traversal attempts
    for (const pattern of Config.REDIRECT_RULES.blockedPatterns) {
        if (target.includes(pattern)) {
            debugLog('Directory traversal attempt detected', pattern);
            return { valid: false, reason: 'Directory traversal attempt' };
        }
    }

    // For hash navigation, ensure it's a simple hash
    if (target.startsWith('#')) {
        // Allow simple hash navigation
        if (/^#[a-zA-Z0-9_-]+$/.test(target)) {
            return { valid: true, reason: 'Valid hash navigation' };
        } else {
            return { valid: false, reason: 'Invalid hash format' };
        }
    }

    // For relative paths, ensure they contain /collabora/
    if (target.startsWith('/')) {
        if (!target.includes(Config.REDIRECT_RULES.requiredPathComponent)) {
            debugLog('Missing required path component', Config.REDIRECT_RULES.requiredPathComponent);
            return { valid: false, reason: 'Must be within collabora application' };
        }

        // Check against login pages to prevent loops
        for (const loginPage of Config.REDIRECT_RULES.loginPages) {
            if (target === loginPage || target === loginPage + '/') {
                debugLog('Redirect to login page blocked', loginPage);
                return { valid: false, reason: 'Cannot redirect to login page' };
            }
        }

        return { valid: true, reason: 'Valid internal path' };
    }

    // If it doesn't start with / or #, it's potentially unsafe
    return { valid: false, reason: 'Must be a relative path starting with / or #' };
}

/**
 * Perform the actual navigation
 * @param {string} target - The validated target URL
 */
function postLoginNavigate(target) {
    debugLog('postLoginNavigate called with', target);

    // Validate the target
    const validation = validateRedirectTarget(target);

    if (!validation.valid) {
        console.warn('Invalid redirect target:', target, 'Reason:', validation.reason);
        debugLog('Validation failed, using default', Config.POST_LOGIN_DEFAULT);

        // Use default safe redirect
        target = Config.POST_LOGIN_DEFAULT;
    }

    debugLog('Final navigation target', target);

    // Check if we should use SPA mode or MPA mode
    if (Config.ENABLE_SPA_MODE) {
        // SPA mode - use client-side routing (if implemented)
        debugLog('SPA mode navigation (not implemented)', target);

        // For now, fallback to full page redirect
        performRedirect(target);
    } else {
        // MPA mode - full page redirect
        performRedirect(target);
    }
}

/**
 * Perform the actual redirect
 * @param {string} target - The target URL
 */
function performRedirect(target) {
    debugLog('Performing redirect to', target);

    // Handle hash navigation differently
    if (target.startsWith('#')) {
        // For hash navigation, update the hash
        window.location.hash = target;

        // Also trigger a hashchange event
        window.dispatchEvent(new HashChangeEvent('hashchange'));

        debugLog('Hash navigation completed', target);
    } else {
        // For path navigation, use href
        window.location.href = target;

        debugLog('Path navigation initiated', target);
    }
}

/**
 * Get the redirect target based on priority
 * @param {object} serverResponse - The server response object
 * @returns {string} - The redirect target URL
 */
function getRedirectTarget(serverResponse = null) {
    debugLog('Getting redirect target', serverResponse);

    let target = null;
    let source = 'default';

    // Priority 1: Check URL query string for 'next' parameter
    const urlParams = new URLSearchParams(window.location.search);
    const nextParam = urlParams.get('next');

    if (nextParam) {
        debugLog('Found next parameter in URL', nextParam);
        const validation = validateRedirectTarget(nextParam);

        if (validation.valid) {
            target = nextParam;
            source = 'query_string';
            debugLog('Using next parameter from query string', target);
        } else {
            debugLog('Query string next parameter invalid', validation.reason);
        }
    }

    // Priority 2: Check server response for redirect field
    if (!target && serverResponse) {
        if (serverResponse.redirect) {
            debugLog('Found redirect in server response', serverResponse.redirect);
            const validation = validateRedirectTarget(serverResponse.redirect);

            if (validation.valid) {
                target = serverResponse.redirect;
                source = 'server_response';
                debugLog('Using redirect from server response', target);
            } else {
                debugLog('Server redirect invalid', validation.reason);
            }
        }

        // Priority 2.5: Check user role for role-based default
        if (!target && serverResponse.user && serverResponse.user.role) {
            const roleDefault = Config.REDIRECT_RULES.roleDefaults[serverResponse.user.role];
            if (roleDefault) {
                target = roleDefault;
                source = 'role_default';
                debugLog('Using role-based default', { role: serverResponse.user.role, target });
            }
        }
    }

    // Priority 3: Use configured default
    if (!target) {
        target = Config.POST_LOGIN_DEFAULT;
        source = 'config_default';
        debugLog('Using configured default', target);
    }

    debugLog('Final redirect decision', { target, source });

    return target;
}

/**
 * Handle post-login flow
 * @param {object} loginResponse - The response from the login API
 */
function handlePostLogin(loginResponse) {
    debugLog('handlePostLogin called', loginResponse);

    // Get the redirect target
    const redirectTarget = getRedirectTarget(loginResponse);

    // Log the decision
    console.log('Login successful. Redirecting to:', redirectTarget);

    // Perform the navigation
    // Add a small delay to ensure any success messages are visible
    setTimeout(() => {
        postLoginNavigate(redirectTarget);
    }, Config.TIMEOUTS?.redirectDelay || 300);
}

// Export functions for use in other scripts
window.PostLoginHandler = {
    validateRedirectTarget,
    postLoginNavigate,
    getRedirectTarget,
    handlePostLogin,
    debugLog
};

// Also export for ES6 modules
export {
    validateRedirectTarget,
    postLoginNavigate,
    getRedirectTarget,
    handlePostLogin
};