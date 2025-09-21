import app from './app.js';

class DashboardModule {
    constructor() {
        this.dashboards = [];
        this.currentDashboardId = null;
        document.addEventListener('collabora:auth-changed', () => this.init());
        document.addEventListener('collabora:tenant-changed', () => this.init());
        document.addEventListener('collabora:page-changed', (event) => {
            if (event.detail.page === 'dashboard-home') {
                this.renderDashboard();
            }
        });
        document.getElementById('new-widget')?.addEventListener('click', () => this.addWidget());
        this.init();
    }

    async init() {
        if (!app.user) return;
        await this.loadDashboards();
        if (this.dashboards.length) {
            this.currentDashboardId = this.dashboards[0].id;
            this.renderDashboard();
        }
    }

    async loadDashboards() {
        try {
            const response = await app.authFetch('api/dashboards.php');
            const data = await response.json();
            this.dashboards = data.dashboards || [];
        } catch (error) {
            console.error(error);
        }
    }

    async renderDashboard() {
        if (!this.currentDashboardId) return;
        try {
            const response = await app.authFetch(`api/widgets.php?dashboard_id=${this.currentDashboardId}`);
            const data = await response.json();
            const widgets = data.widgets || [];
            const container = document.getElementById('dashboard-widgets');
            if (!container) return;
            if (!widgets.length) {
                container.innerHTML = '<p>Nessun widget configurato.</p>';
                return;
            }
            const widgetMarkup = await Promise.all(
                widgets.map(async (widget) => {
                    const infoResponse = await app.authFetch(`api/widgets.php?action=data&id=${widget.id}`);
                    const info = await infoResponse.json();
                    return `
                        <div class="widget">
                            <h4>${widget.title}</h4>
                            <pre>${JSON.stringify(info.data, null, 2)}</pre>
                        </div>
                    `;
                })
            );
            container.innerHTML = widgetMarkup.join('');
        } catch (error) {
            console.error(error);
        }
    }

    async addWidget() {
        if (!this.currentDashboardId) return;
        const type = prompt('Tipo widget (metric/chart/list/gauge)', 'metric');
        if (!type) return;
        const title = prompt('Titolo widget', 'Nuovo widget');
        const payload = {
            dashboard_id: this.currentDashboardId,
            widget_type: type,
            title: title || 'Widget',
            config: {},
        };
        if (type === 'metric') {
            payload.config.metric_name = prompt('Nome metrica', 'hourly_uploads');
        }
        try {
            const response = await app.authFetch('api/widgets.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (data.success) {
                app.showToast('Widget aggiunto', 'success');
                this.renderDashboard();
            } else {
                app.showToast(data.message || 'Errore aggiunta widget', 'error');
            }
        } catch (error) {
            console.error(error);
        }
    }
}

export const dashboardModule = new DashboardModule();
export default dashboardModule;
