class SharingModule {
    constructor() {
        this.list = document.getElementById('share-list');
        this.button = document.getElementById('new-share');
        this.bindEvents();
        if (window.app?.user) {
            this.loadLinks();
        }
    }

    bindEvents() {
        this.button?.addEventListener('click', () => this.createLink());
    }

    async loadLinks() {
        const response = await fetch('api/share-links.php');
        const data = await response.json();
        this.render(data.links || []);
    }

    render(links) {
        if (!this.list) return;
        if (!links.length) {
            this.list.innerHTML = '<p>Nessun link disponibile.</p>';
            return;
        }
        this.list.innerHTML = links.map((link) => `
            <div class="share-item">
                <strong>${link.resource_type} #${link.resource_id}</strong>
                <p>Token: ${link.share_token}</p>
                <small>Scadenza: ${link.expires_at ?? 'Nessuna'}</small>
            </div>
        `).join('');
    }

    async createLink() {
        const resourceId = prompt('ID file da condividere');
        if (!resourceId) return;
        const payload = {
            csrf_token: window.app?.csrfToken || '',
            resource_type: 'file',
            resource_id: parseInt(resourceId, 10),
        };
        const response = await fetch('api/share-links.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });
        const result = await response.json();
        if (result.success) {
            window.app?.showToast('Link generato', 'success');
            this.loadLinks();
        } else {
            window.app?.showToast(result.error || 'Errore creazione link', 'error');
        }
    }
}

window.sharingModule = new SharingModule();
