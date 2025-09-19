// =============================================
// NEXIO COLLABORA - Authentication System JS
// =============================================

// API Configuration - Now using centralized APIConfig
// Fallback to direct detection if APIConfig not available
const API_BASE = window.APIConfig ? window.APIConfig.getApiBaseUrl() : '/collabora/api';

// Load error handler if not already loaded
if (!window.ErrorHandler) {
    const script = document.createElement('script');
    script.src = 'assets/js/error-handler.js';
    document.head.appendChild(script);
}

// Load post-login configuration and handler
if (!window.PostLoginConfig) {
    const configScript = document.createElement('script');
    configScript.src = 'assets/js/post-login-config.js';
    document.head.appendChild(configScript);
}

if (!window.PostLoginHandler) {
    const handlerScript = document.createElement('script');
    handlerScript.src = 'assets/js/post-login-handler.js';
    document.head.appendChild(handlerScript);
}

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// Initialize Application
function initializeApp() {
    // Initialize login form if present
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        initializeLoginForm();
    }

    // Initialize sidebar if present
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        initializeSidebar();
    }

    // Initialize header components
    initializeHeader();

    // Initialize theme
    initializeTheme();

    // Initialize keyboard shortcuts
    initializeKeyboardShortcuts();
}

// =============================================
// LOGIN FUNCTIONALITY
// =============================================

function initializeLoginForm() {
    const loginForm = document.getElementById('loginForm');
    const togglePassword = document.querySelector('.toggle-password');

    // Password visibility toggle
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = this.querySelector('.eye-icon');
            const eyeOffIcon = this.querySelector('.eye-off-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        });
    }

    // Login form submission
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Clear any previous error states
        document.querySelectorAll('.error, .has-error').forEach(el => {
            el.classList.remove('error', 'has-error');
        });

        const formData = new FormData(this);
        // Support both 'email' field name and 'username' field (for autofill compatibility)
        const emailField = document.getElementById('username') || document.getElementById('email');
        const email = emailField ? emailField.value : formData.get('email');
        const password = formData.get('password');
        const remember = formData.get('remember') ? true : false;

        // Validate inputs
        if (!email || !password) {
            showToast('error', 'Errore', 'Email e password sono obbligatori');
            return;
        }

        // Disable submit button
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>Accesso in corso...</span>';

        try {
            // Always send JSON with 'action' field
            const loginPayload = {
                action: 'login',
                email: email,
                password: password,
                remember: remember
            };

            // Log the login attempt in debug mode
            if (window.ErrorHandler?.debugMode) {
                console.log('Login attempt:', { email, remember, payload: loginPayload });
            }

            // Make the request with proper headers and credentials
            const response = await fetch(`${API_BASE}/auth_simple.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'include', // Use 'include' for cookies/sessions
                body: JSON.stringify(loginPayload)
            });

            // Parse the response
            const data = await response.json().catch(() => ({}));

            // Log response in debug mode
            if (window.ErrorHandler?.debugMode) {
                console.log('Response:', { status: response.status, data });
            }

            // Handle the response based on status code
            if (response.status === 200 && data.success) {
                // SUCCESS - 200 with success:true
                showToast('success', 'Accesso riuscito', 'Reindirizzamento in corso...');

                // Debug logging
                console.log('Login successful, user data:', data.user);
                console.log('PostLoginHandler available:', !!window.PostLoginHandler);

                // ALWAYS redirect after successful login - NO EXCEPTIONS
                // Use PostLoginHandler for deterministic navigation
                if (window.PostLoginHandler && typeof window.PostLoginHandler.handlePostLogin === 'function') {
                    console.log('Using PostLoginHandler for redirect');
                    // Use the new post-login handler
                    window.PostLoginHandler.handlePostLogin(data);
                } else {
                    // Fallback to direct redirect if handler not loaded
                    console.warn('PostLoginHandler not available, using direct redirect');

                    // Determine redirect based on user role
                    let redirectUrl = '/Nexiosolution/collabora/home_v2.php'; // Full path with leading slash

                    if (data.user && data.user.role) {
                        switch(data.user.role) {
                            case 'admin':
                                redirectUrl = '/Nexiosolution/collabora/admin/index.php';
                                break;
                            case 'special_user':
                            case 'standard_user':
                            default:
                                redirectUrl = '/Nexiosolution/collabora/home_v2.php';
                        }
                    }

                    console.log('Direct redirect to:', redirectUrl);

                    // CRITICAL: Perform redirect immediately - don't wait for anything
                    // This ensures user NEVER stays on login page after success
                    setTimeout(() => {
                        console.log('Executing redirect now to:', redirectUrl);
                        window.location.href = redirectUrl;
                    }, 500); // Reduced delay to ensure faster redirect
                }

                // ENSURE redirect happens even if above logic fails
                // Absolute failsafe after 2 seconds
                setTimeout(() => {
                    // Check if we're still on the login page
                    if (window.location.pathname.includes('index_v2.php') || window.location.pathname.endsWith('/collabora/')) {
                        console.warn('Failsafe redirect triggered - forcing navigation to home');
                        window.location.href = '/Nexiosolution/collabora/home_v2.php';
                    }
                }, 2000);
            } else if (response.status === 400) {
                // BAD REQUEST - Missing or invalid fields
                let errorMessage = 'Richiesta non valida';
                let errorTitle = 'Dati mancanti';

                if (data.error) {
                    // Extract specific error details
                    if (data.error.fields && data.error.fields.length > 0) {
                        // Show which fields are missing/invalid
                        const fieldNames = data.error.fields.map(f => {
                            if (f === 'email') return 'Email';
                            if (f === 'password') return 'Password';
                            if (f === 'action') return 'Action';
                            return f;
                        }).join(', ');
                        errorMessage = `Campo mancante: ${fieldNames}`;

                        // Add visual indicators to fields
                        data.error.fields.forEach(field => {
                            const input = document.getElementById(field === 'email' ? 'username' : field);
                            if (input) {
                                input.classList.add('error');
                                input.parentElement?.classList.add('has-error');
                            }
                        });
                    } else if (data.error.message) {
                        // Use server-provided message
                        errorMessage = data.error.message;
                    }

                    // Use error code if available
                    if (data.error.code) {
                        if (data.error.code === 'invalid_json') {
                            errorTitle = 'Formato non valido';
                        } else if (data.error.code === 'missing_fields') {
                            errorTitle = 'Campi obbligatori';
                        } else if (data.error.code === 'empty_body') {
                            errorTitle = 'Richiesta vuota';
                        }
                    }
                }

                showToast('error', errorTitle, errorMessage);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            } else if (response.status === 401) {
                // UNAUTHORIZED - Invalid credentials
                let errorMessage = 'Credenziali non valide';

                if (data.error && data.error.message) {
                    errorMessage = data.error.message;
                }

                showToast('error', 'Accesso negato', errorMessage);

                // Add error class to both fields for invalid credentials
                document.getElementById('username')?.classList.add('error');
                document.getElementById('password')?.classList.add('error');

                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            } else if (response.status === 404) {
                // Try auth_v2.php as fallback
                console.log('auth_simple.php not found, trying auth_v2.php');

                const fallbackResponse = await fetch(`${API_BASE}/auth_v2.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify(loginPayload)
                });

                const fallbackData = await fallbackResponse.json().catch(() => ({}));

                if (fallbackResponse.status === 200 && fallbackData.success) {
                    showToast('success', 'Accesso riuscito', 'Reindirizzamento in corso...');

                    console.log('Fallback login successful, user data:', fallbackData.user);

                    // ALWAYS redirect after successful login - NO EXCEPTIONS
                    // Use PostLoginHandler for fallback endpoint too
                    if (window.PostLoginHandler && typeof window.PostLoginHandler.handlePostLogin === 'function') {
                        console.log('Using PostLoginHandler for fallback redirect');
                        window.PostLoginHandler.handlePostLogin(fallbackData);
                    } else {
                        // Determine redirect based on user role (fallback endpoint)
                        let redirectUrl = '/Nexiosolution/collabora/home_v2.php'; // Full path
                        if (fallbackData.user && fallbackData.user.role) {
                            switch(fallbackData.user.role) {
                                case 'admin':
                                    redirectUrl = '/Nexiosolution/collabora/admin/index.php';
                                    break;
                                case 'special_user':
                                case 'standard_user':
                                default:
                                    redirectUrl = '/Nexiosolution/collabora/home_v2.php';
                            }
                        }

                        console.log('Redirecting to (fallback):', redirectUrl);

                        // CRITICAL: Perform redirect immediately
                        setTimeout(() => {
                            console.log('Executing fallback redirect now to:', redirectUrl);
                            window.location.href = redirectUrl;
                        }, 500);
                    }

                    // ENSURE redirect happens - absolute failsafe
                    setTimeout(() => {
                        if (window.location.pathname.includes('index_v2.php') || window.location.pathname.endsWith('/collabora/')) {
                            console.warn('Failsafe redirect triggered (fallback) - forcing navigation');
                            window.location.href = '/Nexiosolution/collabora/home_v2.php';
                        }
                    }, 2000);
                } else {
                    showToast('error', 'Endpoint non trovato', 'Il servizio di autenticazione non è disponibile');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } else if (response.status === 500) {
                // SERVER ERROR
                showToast('error', 'Errore del server', data.error?.message || 'Si è verificato un errore interno');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            } else {
                // Other error
                showToast('error', 'Errore', data.error?.message || data.message || 'Si è verificato un errore durante l\'accesso');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (error) {
            console.error('Login error:', error);

            // Use ErrorHandler if available
            if (window.ErrorHandler) {
                const formatted = window.ErrorHandler.formatError(error);
                showToast(formatted.type, formatted.title, formatted.message);
            } else {
                // Fallback error handling with detailed messages
                let errorTitle = 'Errore di accesso';
                let errorMessage = 'Si è verificato un errore durante l\'accesso';

                if (error.status === 404) {
                    errorTitle = 'Endpoint non trovato';
                    errorMessage = 'Il servizio di autenticazione non è disponibile. Verifica la configurazione.';
                } else if (error.status === 400) {
                    errorTitle = 'Richiesta non valida';
                    errorMessage = error.message || 'I dati inviati non sono validi.';
                } else if (error.status === 401) {
                    errorTitle = 'Credenziali non valide';
                    errorMessage = 'Email o password non corretti.';
                } else if (error.status === 500) {
                    errorTitle = 'Errore del server';
                    errorMessage = 'Si è verificato un errore interno. Contatta il supporto.';
                } else if (error.status === 503) {
                    errorTitle = 'Servizio non disponibile';
                    errorMessage = 'Il servizio è temporaneamente non disponibile. Riprova tra qualche minuto.';
                } else if (error.message) {
                    errorMessage = error.message;
                }

                showToast('error', errorTitle, errorMessage);
            }

            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

// =============================================
// SIDEBAR FUNCTIONALITY
// =============================================

function initializeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');

    // Desktop sidebar toggle
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });

        // Restore sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }
    }

    // Mobile menu toggle
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 &&
                !sidebar.contains(e.target) &&
                !mobileMenuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
    }

    // Tenant selector
    initializeTenantSelector();
}

// Tenant Selector
function initializeTenantSelector() {
    const tenantButton = document.getElementById('tenantButton');
    const tenantMenu = document.getElementById('tenantMenu');

    if (tenantButton && tenantMenu) {
        tenantButton.addEventListener('click', function(e) {
            e.stopPropagation();
            this.parentElement.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            const dropdown = document.querySelector('.tenant-dropdown');
            if (dropdown) {
                dropdown.classList.remove('active');
            }
        });

        // Tenant switching
        const tenantOptions = document.querySelectorAll('.tenant-option');
        tenantOptions.forEach(option => {
            option.addEventListener('click', async function(e) {
                e.stopPropagation();
                const tenantId = this.dataset.tenantId;
                await switchTenant(tenantId);
            });
        });
    }
}

// Switch Tenant
async function switchTenant(tenantId) {
    try {
        let data;

        if (window.APIConfig) {
            data = await APIConfig.post('auth_v2.php', {
                action: 'switch_tenant',
                tenant_id: tenantId
            });
        } else {
            const response = await fetch(`${API_BASE}/auth_v2.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'switch_tenant',
                    tenant_id: tenantId
                })
            });
            data = await response.json();
        }

        if (data.success) {
            showToast('success', 'Tenant cambiato', 'Aggiornamento in corso...');
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } else {
            showToast('error', 'Errore', data.error || 'Impossibile cambiare tenant');
        }
    } catch (error) {
        console.error('Tenant switch error:', error);
        showToast('error', 'Errore', 'Impossibile cambiare tenant');
    }
}

// =============================================
// HEADER FUNCTIONALITY
// =============================================

function initializeHeader() {
    // User menu
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');

    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            userDropdown.classList.add('hidden');
        });
    }

    // Notifications
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationsDropdown = document.getElementById('notificationsDropdown');

    if (notificationBtn && notificationsDropdown) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationsDropdown.classList.toggle('hidden');
            userDropdown?.classList.add('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            notificationsDropdown.classList.add('hidden');
        });

        // Mark all as read
        const markAllRead = notificationsDropdown.querySelector('.mark-all-read');
        if (markAllRead) {
            markAllRead.addEventListener('click', function() {
                markAllNotificationsAsRead();
            });
        }
    }
}

// Mark all notifications as read
async function markAllNotificationsAsRead() {
    try {
        let response, data;

        if (window.APIConfig) {
            data = await APIConfig.post('notifications.php', {
                action: 'mark_all_read'
            });
        } else {
            response = await fetch(`${API_BASE}/notifications.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_all_read'
                })
            });
            data = await response.json();
        }

        if (data.success) {
            // Remove unread classes
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });

            // Update badge
            const badge = document.querySelector('.notification-badge');
            if (badge) {
                badge.style.display = 'none';
            }

            showToast('success', 'Notifiche aggiornate', 'Tutte le notifiche sono state segnate come lette');
        }
    } catch (error) {
        console.error('Mark notifications error:', error);
    }
}

// =============================================
// THEME FUNCTIONALITY
// =============================================

function initializeTheme() {
    const themeToggle = document.getElementById('themeToggle');

    if (themeToggle) {
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';

            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });
    }
}

function updateThemeIcon(theme) {
    const sunIcon = document.querySelector('.sun-icon');
    const moonIcon = document.querySelector('.moon-icon');

    if (theme === 'dark') {
        sunIcon?.classList.add('hidden');
        moonIcon?.classList.remove('hidden');
    } else {
        sunIcon?.classList.remove('hidden');
        moonIcon?.classList.add('hidden');
    }
}

// =============================================
// KEYBOARD SHORTCUTS
// =============================================

function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl+K or Cmd+K for search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.focus();
            }
        }

        // Escape to close dropdowns
        if (e.key === 'Escape') {
            document.querySelectorAll('.user-dropdown, .notifications-dropdown').forEach(dropdown => {
                dropdown.classList.add('hidden');
            });
            document.querySelector('.tenant-dropdown')?.classList.remove('active');
        }
    });
}

// =============================================
// TOAST NOTIFICATIONS
// =============================================

function showToast(type, title, message) {
    const container = document.getElementById('toast-container');

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    // Icon based on type
    let iconSvg = '';
    switch(type) {
        case 'success':
            iconSvg = '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />';
            break;
        case 'error':
            iconSvg = '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />';
            break;
        case 'warning':
            iconSvg = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />';
            break;
        case 'info':
            iconSvg = '<path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />';
            break;
    }

    toast.innerHTML = `
        <svg class="toast-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            ${iconSvg}
        </svg>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    `;

    container.appendChild(toast);

    // Close button
    toast.querySelector('.toast-close').addEventListener('click', function() {
        removeToast(toast);
    });

    // Auto remove after 5 seconds
    setTimeout(() => {
        removeToast(toast);
    }, 5000);
}

function removeToast(toast) {
    toast.style.animation = 'slideOut 0.3s ease forwards';
    setTimeout(() => {
        toast.remove();
    }, 300);
}

// Add slide out animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOut {
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// =============================================
// UTILITY FUNCTIONS
// =============================================

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Format date
function formatDate(date) {
    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(date).toLocaleDateString('it-IT', options);
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Export functions for use in other scripts
window.authV2 = {
    showToast,
    formatFileSize,
    formatDate,
    debounce,
    switchTenant
};