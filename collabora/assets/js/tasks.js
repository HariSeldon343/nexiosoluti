import app from './app.js';

class TaskBoard {
    constructor() {
        this.lists = [];
        this.tasks = { todo: [], in_progress: [], done: [] };
        document.addEventListener('collabora:auth-changed', () => this.init());
        document.addEventListener('collabora:tenant-changed', () => this.init());
        document.addEventListener('collabora:page-changed', (event) => {
            if (event.detail.page === 'tasks') {
                this.renderBoard();
            }
        });
        this.bindEvents();
        this.init();
    }

    bindEvents() {
        document.getElementById('task-list-select')?.addEventListener('change', () => {
            this.loadTasks();
        });
        document.getElementById('new-task')?.addEventListener('click', () => this.createTask());
    }

    async init() {
        if (!app.user) return;
        await this.loadLists();
        await this.loadTasks();
    }

    async loadLists() {
        try {
            const response = await app.authFetch('api/task-lists.php');
            const data = await response.json();
            this.lists = data.lists || [];
            const select = document.getElementById('task-list-select');
            if (select) {
                select.innerHTML = this.lists.map((list) => `<option value="${list.id}">${list.name}</option>`).join('');
            }
        } catch (error) {
            console.error(error);
        }
    }

    getCurrentListId() {
        const select = document.getElementById('task-list-select');
        return select ? parseInt(select.value, 10) : null;
    }

    async loadTasks() {
        const listId = this.getCurrentListId();
        if (!listId) {
            return;
        }
        try {
            const statuses = ['todo', 'in_progress', 'done'];
            const results = await Promise.all(statuses.map((status) => app.authFetch(`api/tasks.php?list_id=${listId}&status=${status}`)));
            for (let i = 0; i < statuses.length; i++) {
                const data = await results[i].json();
                this.tasks[statuses[i]] = data.tasks || [];
            }
            this.renderBoard();
        } catch (error) {
            console.error(error);
        }
    }

    renderBoard() {
        document.querySelectorAll('.kanban-column').forEach((column) => {
            const status = column.getAttribute('data-status');
            const list = this.tasks[status] || [];
            const container = column.querySelector('.kanban-cards');
            const count = column.querySelector('.count');
            if (count) {
                count.textContent = `(${list.length})`;
            }
            if (container) {
                container.innerHTML = list
                    .map(
                        (task) => `
                        <div class="kanban-card" draggable="true" data-id="${task.id}">
                            <div class="task-title">${task.title}</div>
                            <div class="task-meta">${task.assigned_name || ''}</div>
                        </div>`
                    )
                    .join('');
            }
        });
    }

    async createTask() {
        const listId = this.getCurrentListId();
        if (!listId) return;
        const title = prompt('Titolo del task');
        if (!title) return;
        try {
            const response = await app.authFetch('api/tasks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ task_list_id: listId, title }),
            });
            const data = await response.json();
            if (data.success) {
                app.showToast('Task creato', 'success');
                this.loadTasks();
            } else {
                app.showToast(data.message || 'Errore creazione task', 'error');
            }
        } catch (error) {
            console.error(error);
        }
    }
}

export const taskBoard = new TaskBoard();
export default taskBoard;
