import app from './app.js';

class CalendarModule {
    constructor() {
        this.currentDate = new Date();
        this.events = [];
        document.addEventListener('collabora:auth-changed', () => this.init());
        document.addEventListener('collabora:tenant-changed', () => this.init());
        document.addEventListener('collabora:page-changed', (event) => {
            if (event.detail.page === 'calendar') {
                this.render();
            }
        });
        this.bindEvents();
        this.init();
    }

    bindEvents() {
        document.getElementById('cal-prev')?.addEventListener('click', () => {
            this.currentDate.setMonth(this.currentDate.getMonth() - 1);
            this.loadEvents();
        });
        document.getElementById('cal-next')?.addEventListener('click', () => {
            this.currentDate.setMonth(this.currentDate.getMonth() + 1);
            this.loadEvents();
        });
        document.getElementById('new-event')?.addEventListener('click', () => this.createEvent());
    }

    init() {
        if (!app.user) {
            return;
        }
        this.loadEvents();
    }

    async loadEvents() {
        const start = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1).toISOString().slice(0, 10);
        const end = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0).toISOString().slice(0, 10);
        try {
            const response = await app.authFetch(`api/events.php?start=${start}&end=${end}`);
            const data = await response.json();
            this.events = data.events || [];
            this.render();
        } catch (error) {
            console.error(error);
        }
    }

    render() {
        const monthNames = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
        document.getElementById('cal-month').textContent = `${monthNames[this.currentDate.getMonth()]} ${this.currentDate.getFullYear()}`;
        const grid = document.getElementById('calendar-grid');
        if (!grid) return;
        const firstDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
        const startDay = firstDay.getDay() || 7;
        const daysInMonth = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0).getDate();
        let html = '<div class="calendar-head">Lun</div><div class="calendar-head">Mar</div><div class="calendar-head">Mer</div><div class="calendar-head">Gio</div><div class="calendar-head">Ven</div><div class="calendar-head">Sab</div><div class="calendar-head">Dom</div>';
        for (let i = 1; i < startDay; i++) {
            html += '<div class="calendar-cell empty"></div>';
        }
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${this.currentDate.getFullYear()}-${String(this.currentDate.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const events = this.events.filter((event) => event.start_datetime.startsWith(dateStr));
            html += `<div class="calendar-cell"><div class="calendar-day">${day}</div>${events.map((event) => `<div class="calendar-event" title="${event.title}">${event.title}</div>`).join('')}</div>`;
        }
        grid.innerHTML = html;
    }

    async createEvent() {
        const title = prompt('Titolo evento');
        if (!title) {
            return;
        }
        const start = prompt('Data inizio (YYYY-MM-DD HH:MM)', `${new Date().toISOString().slice(0, 16).replace('T', ' ')}`);
        const end = prompt('Data fine (YYYY-MM-DD HH:MM)', `${new Date().toISOString().slice(0, 16).replace('T', ' ')}`);
        if (!start || !end) {
            return;
        }
        const payload = {
            calendar_id: 1,
            title,
            start_datetime: start,
            end_datetime: end,
            description: ''
        };
        try {
            const response = await app.authFetch('api/events.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (data.success) {
                app.showToast('Evento creato', 'success');
                this.loadEvents();
            } else {
                app.showToast(data.message || 'Errore creazione evento', 'error');
            }
        } catch (error) {
            console.error(error);
        }
    }
}

export const calendarModule = new CalendarModule();
export default calendarModule;
