class CalendarModule {
    constructor() {
        this.currentDate = new Date();
        this.view = 'month';
        this.bindEvents();
        if (window.app?.user) {
            this.render();
        }
    }

    bindEvents() {
        document.getElementById('cal-prev')?.addEventListener('click', () => {
            this.changeMonth(-1);
        });
        document.getElementById('cal-next')?.addEventListener('click', () => {
            this.changeMonth(1);
        });
        document.querySelectorAll('.calendar-views .view-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                this.view = btn.dataset.view;
                document.querySelectorAll('.calendar-views .view-btn').forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');
                this.render();
            });
        });
    }

    changeMonth(delta) {
        this.currentDate.setMonth(this.currentDate.getMonth() + delta);
        this.render();
    }

    async render() {
        const monthName = this.currentDate.toLocaleString('it-IT', {month: 'long', year: 'numeric'});
        const start = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1).toISOString().substring(0, 10);
        const end = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0).toISOString().substring(0, 10);
        document.getElementById('cal-month').textContent = monthName.charAt(0).toUpperCase() + monthName.slice(1);
        try {
            const response = await fetch(`api/events.php?start=${start}&end=${end}`);
            const data = await response.json();
            this.renderMonth(data.events || []);
        } catch (error) {
            console.error('Errore calendario', error);
        }
    }

    renderMonth(events) {
        const grid = document.getElementById('calendar-grid');
        if (!grid) return;
        const firstDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
        const startDay = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;
        const daysInMonth = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0).getDate();
        let html = '<div class="calendar-header">Lun</div><div class="calendar-header">Mar</div><div class="calendar-header">Mer</div><div class="calendar-header">Gio</div><div class="calendar-header">Ven</div><div class="calendar-header">Sab</div><div class="calendar-header">Dom</div>';
        for (let i = 0; i < startDay; i++) {
            html += '<div class="calendar-cell empty"></div>';
        }
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), day).toISOString().substring(0, 10);
            const dayEvents = events.filter((event) => event.start_datetime?.startsWith(dateStr));
            html += `<div class="calendar-cell"><div class="date">${day}</div>`;
            dayEvents.forEach((event) => {
                html += `<div class="event-chip">${event.title}</div>`;
            });
            html += '</div>';
        }
        grid.innerHTML = html;
    }
}

window.calendarModule = new CalendarModule();
