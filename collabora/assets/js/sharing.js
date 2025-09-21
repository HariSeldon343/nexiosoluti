import app from './app.js';

class SharingModule {
    constructor() {
        this.links = [];
        document.addEventListener('collabora:auth-changed', () => this.reset());
        document.addEventListener('collabora:tenant-changed', () => this.reset());
        document.getElementById('new-share-link')?.addEventListener('click', () => this.createShare());
    }

    reset() {
        this.links = [];
        this.render();
    }

    render() {
        const container = document.getElementById('share-list');
        if (!container) return;
        if (!this.links.length) {
            container.innerHTML = '<p>Nessun link di condivisione creato.</p>';
            return;
        }
        container.innerHTML = this.links
            .map((link) => `
                <div class="share-item">
                    <div><strong>Risorsa:</strong> ${link.resource_type} #${link.resource_id}</div>
                    <div><strong>URL:</strong> <a href="${link.url}" target="_blank">${link.url}</a></div>
                    <div><strong>Scadenza:</strong> ${link.expires_at || 'Nessuna'}</div>
                </div>
            `)
            .join('');
    }

    async createShare() {
        const resourceId = prompt('ID file da condividere');
        if (!resourceId) return;
        const expiresAt = prompt('Data scadenza (opzionale YYYY-MM-DD)');
        const password = prompt('Password (opzionale)');
        const payload = {
            resource_type: 'file',
            resource_id: parseInt(resourceId, 10),
            expires_at: expiresAt || null,
            password: password || null,
        };
        try {
            const response = await app.authFetch('api/share-links.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (data.success) {
                app.showToast('Link di condivisione creato', 'success');
                this.links.unshift({ ...payload, url: data.url });
                this.render();
            } else {
                app.showToast(data.message || 'Errore creazione link', 'error');
            }
        } catch (error) {
            console.error(error);
        }
    }
}

export const sharingModule = new SharingModule();
export default sharingModule;
