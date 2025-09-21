const initialState = window.__COLLABORA__ || {};

class CollaboraApp {
    constructor() {
        this.user = initialState.user || null;
        this.csrfToken = initialState.csrfToken || '';
        this.currentPage = 'files';
        this.navItems = Array.from(document.querySelectorAll('.sidebar-nav .nav-item'));
        this.init();
    }

    async init() {
        this.bindEvents();
        if (this.user) {
            this.showDashboard();
        } else {
            this.showLogin();
        }
    }

    bindEvents() {
        const loginForm = document.getElementById('login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', (event) => {
                event.preventDefault();
                this.login();
            });
        }

        const logoutBtn = document.getElementById('logout');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => this.logout());
        }

        const sidebarToggle = document.getElementById('sidebar-toggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.toggle('collapsed');
                sidebar.classList.toggle('open');
            });
        }

        this.navItems.forEach((item) => {
            item.addEventListener('click', (event) => {
                event.preventDefault();
                const page = item.getAttribute('data-page');
                if (page) {
                    this.switchPage(page);
                }
            });
        });
    }

    async login() {
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        if (!emailInput || !passwordInput) {
            return;
        }

        const payload = {
            email: emailInput.value.trim(),
            password: passwordInput.value,
        };

        try {
            const response = await fetch('api/auth.php?action=login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (data.success) {
                this.user = data.user;
                if (data.csrfToken) {
                    this.csrfToken = data.csrfToken;
                }
                this.showDashboard();
                document.dispatchEvent(new CustomEvent('collabora:auth-changed', { detail: this.user }));
                this.showToast('Accesso effettuato', 'success');
            } else {
                this.showToast(data.message || 'Credenziali non valide', 'error');
            }
        } catch (error) {
            console.error(error);
            this.showToast('Errore di rete durante il login', 'error');
        }
    }

    async logout() {
        try {
            await fetch('api/auth.php?action=logout', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.csrfToken,
                },
            });
        } catch (error) {
            console.warn('Logout con avviso:', error);
        }
        window.location.reload();
    }

    showDashboard() {
        document.getElementById('login-panel')?.classList.add('hidden');
        document.getElementById('dashboard')?.classList.remove('hidden');
        document.getElementById('user-name').textContent = this.user?.name || '';
        document.getElementById('user-role').textContent = this.user?.role || '';
        this.updateTenantSwitch();
        this.switchPage(this.currentPage);
    }

    showLogin() {
        document.getElementById('login-panel')?.classList.remove('hidden');
        document.getElementById('dashboard')?.classList.add('hidden');
    }

    switchPage(page) {
        this.currentPage = page;
        document.querySelectorAll('.page').forEach((el) => el.classList.add('hidden'));
        const target = document.getElementById(`${page}-page`);
        if (target) {
            target.classList.remove('hidden');
        }
        this.navItems.forEach((item) => {
            item.classList.toggle('active', item.getAttribute('data-page') === page);
        });
        document.dispatchEvent(new CustomEvent('collabora:page-changed', { detail: { page } }));
    }

    showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('hidden');
            toast.remove();
        }, 3000);
    }

    updateTenantSwitch() {
        const container = document.getElementById('tenant-switch');
        if (!container || !this.user) return;
        container.innerHTML = '';
        const tenants = this.user.tenants || [];
        if (tenants.length <= 1) {
            if (tenants[0]) {
                const span = document.createElement('span');
                span.textContent = tenants[0].name;
                container.appendChild(span);
            }
            return;
        }
        const select = document.createElement('select');
        tenants.forEach((tenant) => {
            const option = document.createElement('option');
            option.value = tenant.id;
            option.textContent = tenant.name;
            if (tenant.id === this.user.tenant_id) {
                option.selected = true;
            }
            select.appendChild(option);
        });
        select.addEventListener('change', () => {
            this.switchTenant(parseInt(select.value, 10));
        });
        container.appendChild(select);
    }

    async switchTenant(tenantId) {
        try {
            const response = await fetch('api/auth.php?action=switchTenant', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken,
                },
                body: JSON.stringify({ tenant_id: tenantId }),
            });
            const data = await response.json();
            if (data.success) {
                this.user = data.user;
                if (data.csrfToken) {
                    this.csrfToken = data.csrfToken;
                }
                this.showDashboard();
                document.dispatchEvent(new CustomEvent('collabora:tenant-changed', { detail: { tenantId } }));
            } else {
                this.showToast(data.message || 'Cambio tenant non riuscito', 'error');
            }
        } catch (error) {
            console.error(error);
            this.showToast('Errore durante il cambio tenant', 'error');
        }
    }

    async authFetch(url, options = {}) {
        const headers = options.headers || {};
        if (!('X-CSRF-Token' in headers) && options.method && options.method !== 'GET') {
            headers['X-CSRF-Token'] = this.csrfToken;
        }
        const response = await fetch(url, { ...options, headers });
        if (response.status === 401) {
            window.location.reload();
            return Promise.reject(new Error('Sessione scaduta'));
        }
        return response;
    }
}

export const app = new CollaboraApp();
export default app;
