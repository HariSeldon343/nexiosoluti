class TasksModule {
    constructor() {
        this.listSelect = document.getElementById('task-list-select');
        this.columns = document.querySelectorAll('.kanban-column');
        this.bindEvents();
        if (window.app?.user) {
            this.loadTasks();
        }
    }

    bindEvents() {
        this.listSelect?.addEventListener('change', () => this.loadTasks());
        document.getElementById('new-task')?.addEventListener('click', () => this.newTask());
    }

    async loadTasks() {
        const listId = this.listSelect?.value;
        if (!listId) return;
        const response = await fetch(`api/tasks.php?list_id=${listId}`);
        const data = await response.json();
        this.render(data.tasks || []);
    }

    render(tasks) {
        this.columns.forEach((column) => {
            const status = column.dataset.status;
            const cards = column.querySelector('.kanban-cards');
            const items = tasks.filter((task) => task.status === status);
            column.querySelector('.count').textContent = `(${items.length})`;
            cards.innerHTML = items.map((task) => `
                <div class="kanban-card" data-id="${task.id}">
                    <strong>${task.title}</strong>
                    <p>${task.description ?? ''}</p>
                    <small>${task.due_date ?? ''}</small>
                </div>
            `).join('');
        });
    }

    async newTask() {
        const title = prompt('Titolo del task');
        if (!title) return;
        const payload = {
            csrf_token: window.app?.csrfToken || '',
            task_list_id: this.listSelect?.value,
            title,
        };
        const response = await fetch('api/tasks.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });
        const result = await response.json();
        if (result.success) {
            window.app?.showToast('Task creato', 'success');
            this.loadTasks();
        } else {
            window.app?.showToast(result.error || 'Errore creazione task', 'error');
        }
    }
}

window.tasksModule = new TasksModule();
