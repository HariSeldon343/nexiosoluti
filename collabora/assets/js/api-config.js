/**
 * API Configuration Module
 *
 * Centralized API configuration that dynamically detects base URL
 * Works regardless of installation location (/collabora/, /Nexiosolution/collabora/, etc.)
 *
 * Priority order for base URL resolution:
 * 1. Global window.API_CONFIG.baseUrl if defined
 * 2. Data attribute from script tag (data-api-base)
 * 3. Automatic detection from current page URL
 *
 * @module APIConfig
 */

(function(window) {
    'use strict';

    /**
     * Detect the base path dynamically from current URL
     * Searches for '/collabora/' in the path and extracts everything before it
     *
     * @returns {string} The detected base path
     */
    function detectBasePath() {
        const pathname = window.location.pathname;

        // Look for '/collabora/' in the current path
        const collaboraIndex = pathname.indexOf('/collabora/');

        if (collaboraIndex !== -1) {
            // Extract everything up to and including '/collabora'
            return pathname.substring(0, collaboraIndex + '/collabora'.length);
        }

        // Fallback: try to detect from script src
        const scripts = document.getElementsByTagName('script');
        for (let script of scripts) {
            const src = script.src;
            if (src && src.includes('/collabora/assets/js/')) {
                const url = new URL(src);
                const pathIndex = url.pathname.indexOf('/collabora/assets/js/');
                if (pathIndex !== -1) {
                    return url.pathname.substring(0, pathIndex + '/collabora'.length);
                }
            }
        }

        // Last resort fallback
        console.warn('APIConfig: Could not auto-detect base path, using /collabora as fallback');
        return '/collabora';
    }

    /**
     * Get the API base URL
     * Checks multiple sources in priority order
     *
     * @returns {string} The API base URL
     */
    function getApiBaseUrl() {
        // Priority 1: Global configuration
        if (window.API_CONFIG && window.API_CONFIG.baseUrl) {
            return window.API_CONFIG.baseUrl;
        }

        // Priority 2: Data attribute on current script
        const currentScript = document.currentScript;
        if (currentScript && currentScript.dataset.apiBase) {
            return currentScript.dataset.apiBase;
        }

        // Priority 3: Auto-detect from current URL
        const basePath = detectBasePath();
        return basePath + '/api';
    }

    /**
     * Build a full API URL from an endpoint
     *
     * @param {string} endpoint - The API endpoint (e.g., 'auth_v2.php', 'files.php')
     * @returns {string} The full API URL
     */
    function buildApiUrl(endpoint) {
        const baseUrl = getApiBaseUrl();

        // Remove leading slash from endpoint if present
        const cleanEndpoint = endpoint.startsWith('/') ? endpoint.substring(1) : endpoint;

        // Ensure baseUrl doesn't end with slash
        const cleanBase = baseUrl.endsWith('/') ? baseUrl.slice(0, -1) : baseUrl;

        return `${cleanBase}/${cleanEndpoint}`;
    }

    /**
     * Get CSRF token from meta tag or storage
     *
     * @returns {string|null} The CSRF token or null if not found
     */
    function getCsrfToken() {
        // Try meta tag first
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.content;
        }

        // Try sessionStorage
        const stored = sessionStorage.getItem('csrf_token');
        if (stored) {
            return stored;
        }

        // Try global variable (legacy support)
        if (window.csrfToken) {
            return window.csrfToken;
        }

        return null;
    }

    /**
     * Default fetch options with common headers
     *
     * @returns {Object} Default fetch options
     */
    function getDefaultFetchOptions() {
        const options = {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const csrfToken = getCsrfToken();
        if (csrfToken) {
            options.headers['X-CSRF-Token'] = csrfToken;
        }

        return options;
    }

    /**
     * Make an API request with proper error handling
     *
     * @param {string} endpoint - The API endpoint
     * @param {Object} options - Fetch options
     * @returns {Promise} Promise resolving to response data
     */
    async function apiRequest(endpoint, options = {}) {
        const url = buildApiUrl(endpoint);
        const defaultOptions = getDefaultFetchOptions();

        // Merge options
        const finalOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {})
            }
        };

        // Add Content-Type for JSON payloads
        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            finalOptions.headers['Content-Type'] = 'application/json';
            finalOptions.body = JSON.stringify(options.body);
        }

        try {
            const response = await fetch(url, finalOptions);

            // Handle different response statuses
            if (response.status === 404) {
                throw new Error(`API endpoint not found: ${endpoint}`);
            }

            if (response.status === 401) {
                // Unauthorized - might need to redirect to login
                const data = await response.json().catch(() => null);
                if (data && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                throw new Error('Unauthorized');
            }

            if (!response.ok) {
                const errorData = await response.json().catch(() => null);
                throw new Error(errorData?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            // Parse JSON response
            const data = await response.json();
            return data;

        } catch (error) {
            // Log error for debugging
            console.error('API Request Error:', {
                endpoint,
                url,
                error: error.message
            });

            throw error;
        }
    }

    /**
     * POST request helper
     *
     * @param {string} endpoint - The API endpoint
     * @param {Object} data - Request data
     * @param {Object} options - Additional fetch options
     * @returns {Promise} Promise resolving to response data
     */
    function post(endpoint, data = {}, options = {}) {
        return apiRequest(endpoint, {
            ...options,
            method: 'POST',
            body: data
        });
    }

    /**
     * GET request helper
     *
     * @param {string} endpoint - The API endpoint
     * @param {Object} params - Query parameters
     * @param {Object} options - Additional fetch options
     * @returns {Promise} Promise resolving to response data
     */
    function get(endpoint, params = {}, options = {}) {
        // Build query string
        const queryString = new URLSearchParams(params).toString();
        const finalEndpoint = queryString ? `${endpoint}?${queryString}` : endpoint;

        return apiRequest(finalEndpoint, {
            ...options,
            method: 'GET'
        });
    }

    /**
     * PUT request helper
     *
     * @param {string} endpoint - The API endpoint
     * @param {Object} data - Request data
     * @param {Object} options - Additional fetch options
     * @returns {Promise} Promise resolving to response data
     */
    function put(endpoint, data = {}, options = {}) {
        return apiRequest(endpoint, {
            ...options,
            method: 'PUT',
            body: data
        });
    }

    /**
     * DELETE request helper
     *
     * @param {string} endpoint - The API endpoint
     * @param {Object} options - Additional fetch options
     * @returns {Promise} Promise resolving to response data
     */
    function deleteRequest(endpoint, options = {}) {
        return apiRequest(endpoint, {
            ...options,
            method: 'DELETE'
        });
    }

    /**
     * Upload file with progress tracking
     *
     * @param {string} endpoint - The API endpoint
     * @param {FormData} formData - Form data with file(s)
     * @param {Function} onProgress - Progress callback (percentage)
     * @param {AbortController} controller - Abort controller for cancellation
     * @returns {Promise} Promise resolving to response data
     */
    function uploadWithProgress(endpoint, formData, onProgress = null, controller = null) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const url = buildApiUrl(endpoint);

            // Track upload progress
            if (onProgress) {
                xhr.upload.addEventListener('progress', (event) => {
                    if (event.lengthComputable) {
                        const percentage = Math.round((event.loaded / event.total) * 100);
                        onProgress(percentage);
                    }
                });
            }

            // Handle abort
            if (controller) {
                controller.signal.addEventListener('abort', () => {
                    xhr.abort();
                    reject(new Error('Upload cancelled'));
                });
            }

            // Handle completion
            xhr.addEventListener('load', () => {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve(response);
                    } else {
                        reject(new Error(response.message || `HTTP ${xhr.status}`));
                    }
                } catch (error) {
                    reject(new Error('Invalid server response'));
                }
            });

            // Handle errors
            xhr.addEventListener('error', () => {
                reject(new Error('Network error during upload'));
            });

            // Setup request
            xhr.open('POST', url);

            // Add headers
            const defaultOptions = getDefaultFetchOptions();
            for (const [key, value] of Object.entries(defaultOptions.headers)) {
                xhr.setRequestHeader(key, value);
            }

            // Send request
            xhr.send(formData);
        });
    }

    /**
     * Initialize the API configuration
     * Can be called manually to reinitialize with new settings
     *
     * @param {Object} config - Configuration options
     */
    function initialize(config = {}) {
        if (config.baseUrl) {
            window.API_CONFIG = window.API_CONFIG || {};
            window.API_CONFIG.baseUrl = config.baseUrl;
        }

        // Log current configuration for debugging
        console.log('APIConfig initialized:', {
            baseUrl: getApiBaseUrl(),
            detected: detectBasePath()
        });
    }

    /**
     * API Configuration Module Public Interface
     */
    const APIConfig = {
        // Core functions
        initialize,
        getApiBaseUrl,
        buildApiUrl,
        getCsrfToken,

        // Request helpers
        request: apiRequest,
        get,
        post,
        put,
        delete: deleteRequest,
        uploadWithProgress,

        // Utility functions
        detectBasePath,
        getDefaultFetchOptions,

        // Backwards compatibility
        getBaseUrl: getApiBaseUrl,

        // Version info
        version: '2.0.0'
    };

    // Export to global scope
    window.APIConfig = APIConfig;

    // Auto-initialize on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initialize());
    } else {
        initialize();
    }

})(window);