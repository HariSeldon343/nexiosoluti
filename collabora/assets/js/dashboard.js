class DashboardModule {
    constructor() {
        this.container = document.getElementById('dashboard-widgets');
        this.button = document.getElementById('add-widget');
        this.bindEvents();
        if (window.app?.user) {
            this.load();
        }
    }

    bindEvents() {
        this.button?.addEventListener('click', () => this.addWidget());
    }

    async load() {
        const response = await fetch('api/dashboards.php');
        const data = await response.json();
        this.dashboards = data.dashboards || [];
        if (this.dashboards.length) {
            this.renderDashboard(this.dashboards[0]);
        }
    }

    async addWidget() {
        const metric = prompt('Nome metrica (es. hourly_uploads)');
        if (!metric) return;
        const payload = {
            csrf_token: window.app?.csrfToken || '',
            type: 'metric',
            config: {metric_name: metric},
        };
        const response = await fetch('api/widgets.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });
        const result = await response.json();
        this.renderWidget({title: metric, data: result});
    }

    renderDashboard(dashboard) {
        if (!this.container) return;
        this.container.innerHTML = '';
        const layout = Array.isArray(dashboard.layout) ? dashboard.layout : [];
        layout.forEach((widget) => this.renderWidget(widget));
    }

    renderWidget(widget) {
        const card = document.createElement('div');
        card.className = 'widget-card';
        card.innerHTML = `
            <h3>${widget.title || widget.metric_name || 'Widget'}</h3>
            <p>Valore: ${widget.data?.value ?? ''}</p>
        `;
        this.container?.appendChild(card);
    }
}

window.dashboardModule = new DashboardModule();
