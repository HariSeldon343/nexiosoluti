class CollaboraApp {
    constructor() {
        this.user = window.__APP_DATA__?.user || null;
        this.currentPage = 'files';
        this.csrfToken = this.user?.csrf_token || document.getElementById('csrf-token')?.value || '';
        this.init();
    }

    async init() {
        if (!this.user) {
            await this.checkAuth();
        } else {
            this.showDashboard();
        }

        this.bindEvents();
    }

    async checkAuth() {
        try {
            const response = await fetch('api/auth.php?action=check');
            const data = await response.json();
            if (data.authenticated) {
                this.user = data.user;
                this.csrfToken = data.user.csrf_token;
                this.showDashboard();
            } else {
                this.showLogin();
            }
        } catch (error) {
            console.error('Errore verifica sessione', error);
            this.showLogin();
        }
    }

    bindEvents() {
        document.getElementById('login-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.login();
        });

        document.getElementById('logout')?.addEventListener('click', () => {
            this.logout();
        });

        document.querySelectorAll('.nav-item').forEach((item) => {
            item.addEventListener('click', (event) => {
                event.preventDefault();
                const page = item.getAttribute('data-page');
                this.switchPage(page);
            });
        });

        document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('sidebar').classList.toggle('open');
        });
    }

    async login() {
        const payload = {
            tenant_code: document.getElementById('tenant-code').value.trim(),
            email: document.getElementById('email').value.trim(),
            password: document.getElementById('password').value,
            csrf_token: this.csrfToken,
        };

        const response = await fetch('api/auth.php?action=login', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });

        const result = await response.json();
        if (result.success) {
            this.user = result.user;
            this.csrfToken = result.user.csrf_token;
            this.showDashboard();
            window.fileManager?.refreshAfterLogin();
        } else {
            this.showToast(result.error || 'Credenziali non valide', 'error');
        }
    }

    async logout() {
        await fetch('api/auth.php?action=logout', {
            method: 'POST',
            headers: {'X-CSRF-Token': this.csrfToken},
        });
        window.location.reload();
    }

    showDashboard() {
        document.getElementById('login-panel').classList.add('hidden');
        document.getElementById('dashboard').classList.remove('hidden');
        document.getElementById('user-name').textContent = this.user?.name || '';
        document.getElementById('user-role').textContent = this.user?.role || '';
    }

    showLogin() {
        document.getElementById('login-panel').classList.remove('hidden');
        document.getElementById('dashboard').classList.add('hidden');
    }

    switchPage(page) {
        if (!page) return;
        this.currentPage = page;
        document.querySelectorAll('.page').forEach((p) => p.classList.add('hidden'));
        document.querySelectorAll('.nav-item').forEach((item) => item.classList.remove('active'));
        document.getElementById(`${page}`)?.classList.remove('hidden');
        document.getElementById(`${page}-page`)?.classList.remove('hidden');
        document.querySelector(`.nav-item[data-page="${page}"]`)?.classList.add('active');
    }

    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.getElementById('toast-container').appendChild(toast);
        setTimeout(() => toast.remove(), 3500);
    }
}

window.app = new CollaboraApp();
