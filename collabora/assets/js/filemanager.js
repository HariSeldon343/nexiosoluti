class FileManager {
    constructor() {
        this.currentFolder = null;
        this.files = [];
        this.folders = [];
        this.viewMode = 'grid';
        this.initialized = false;
        this.bindEvents();
        if (window.app?.user) {
            this.init();
        }
    }

    refreshAfterLogin() {
        if (!this.initialized) {
            this.init();
        }
    }

    init() {
        this.initialized = true;
        this.loadFolder(null);
        this.loadFolderTree();
        this.setupDragDrop();
    }

    bindEvents() {
        document.getElementById('upload-btn')?.addEventListener('click', () => {
            document.getElementById('file-input').click();
        });

        document.getElementById('file-input')?.addEventListener('change', (e) => {
            this.uploadFiles(e.target.files);
        });

        document.getElementById('new-folder-btn')?.addEventListener('click', () => {
            const name = prompt('Nome nuova cartella');
            if (name) {
                this.createFolder(name);
            }
        });

        document.getElementById('view-toggle')?.addEventListener('click', () => {
            this.toggleView();
        });
    }

    setupDragDrop() {
        const dropZone = document.body;
        if (!dropZone) return;

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            document.getElementById('drop-zone')?.classList.remove('hidden');
        });

        dropZone.addEventListener('dragleave', (e) => {
            if (e.target === dropZone) {
                document.getElementById('drop-zone')?.classList.add('hidden');
            }
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            document.getElementById('drop-zone')?.classList.add('hidden');
            this.uploadFiles(e.dataTransfer.files);
        });
    }

    async loadFolder(folderId) {
        try {
            const response = await fetch(`api/files.php?folder_id=${folderId ?? ''}`);
            const data = await response.json();
            this.currentFolder = folderId;
            this.files = data.files || [];
            this.renderFiles();
            this.updateBreadcrumb(data.breadcrumb || []);
        } catch (error) {
            console.error('Errore caricamento file', error);
        }
    }

    async loadFolderTree() {
        try {
            const response = await fetch('api/folders.php');
            const data = await response.json();
            this.folders = data.folders || [];
            this.renderFolderTree();
        } catch (error) {
            console.error('Errore caricamento cartelle', error);
        }
    }

    async uploadFiles(files) {
        if (!files || files.length === 0) return;
        for (const file of files) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('folder_id', this.currentFolder ?? '');
            formData.append('csrf_token', window.app?.csrfToken || '');

            const response = await fetch('api/files.php', {
                method: 'POST',
                body: formData,
            });

            const result = await response.json();
            if (result.success) {
                window.app?.showToast(`File ${file.name} caricato`, 'success');
            } else {
                window.app?.showToast(result.error || `Errore caricamento ${file.name}`, 'error');
            }
        }
        this.loadFolder(this.currentFolder);
    }

    async createFolder(name) {
        const response = await fetch('api/folders.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                name,
                parent_id: this.currentFolder,
                csrf_token: window.app?.csrfToken || '',
            }),
        });
        const result = await response.json();
        if (result.success) {
            window.app?.showToast('Cartella creata', 'success');
            this.loadFolderTree();
            this.loadFolder(this.currentFolder);
        } else {
            window.app?.showToast(result.error || 'Errore creazione cartella', 'error');
        }
    }

    toggleView() {
        this.viewMode = this.viewMode === 'grid' ? 'list' : 'grid';
        this.renderFiles();
    }

    renderFiles() {
        const container = document.getElementById('file-grid');
        if (!container) return;
        container.className = this.viewMode === 'grid' ? 'file-grid' : 'file-list';
        if (!this.files.length) {
            container.innerHTML = '<p>Nessun file presente.</p>';
            return;
        }
        container.innerHTML = this.files.map((file) => `
            <div class="file-item" data-id="${file.id}">
                <div class="file-icon">📄</div>
                <div class="file-name">${file.original_name}</div>
                <div class="file-size">${this.formatSize(file.size)}</div>
                <div class="file-actions">
                    <button type="button" data-download="${file.id}">⬇</button>
                    <button type="button" data-delete="${file.id}">🗑</button>
                </div>
            </div>
        `).join('');

        container.querySelectorAll('[data-download]').forEach((btn) => {
            btn.addEventListener('click', () => this.downloadFile(btn.dataset.download));
        });
        container.querySelectorAll('[data-delete]').forEach((btn) => {
            btn.addEventListener('click', () => this.deleteFile(btn.dataset.delete));
        });
    }

    renderFolderTree() {
        const tree = document.getElementById('folder-tree');
        if (!tree) return;
        const renderNode = (folder, level = 0) => {
            const indent = '&nbsp;'.repeat(level * 4);
            const children = this.folders.filter((f) => f.parent_id === folder.id);
            let html = `<div class="folder-node" data-id="${folder.id}">${indent}📁 ${folder.name}</div>`;
            children.forEach((child) => {
                html += renderNode(child, level + 1);
            });
            return html;
        };
        const roots = this.folders.filter((f) => f.parent_id === null);
        tree.innerHTML = roots.map((root) => renderNode(root)).join('');
        tree.querySelectorAll('.folder-node').forEach((node) => {
            node.addEventListener('click', () => {
                const id = node.dataset.id === 'null' ? null : parseInt(node.dataset.id, 10);
                this.loadFolder(id);
            });
        });
    }

    updateBreadcrumb(breadcrumb = []) {
        const container = document.getElementById('breadcrumb');
        if (!container) return;
        const items = [{id: null, name: 'Home'}].concat(breadcrumb);
        container.innerHTML = items.map((item) => `<a href="#" data-folder="${item.id ?? ''}">${item.name}</a>`).join(' / ');
        container.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const id = link.dataset.folder ? parseInt(link.dataset.folder, 10) : null;
                this.loadFolder(Number.isNaN(id) ? null : id);
            });
        });
    }

    formatSize(bytes) {
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        if (!bytes) return '0 B';
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`;
    }

    downloadFile(fileId) {
        window.location.href = `api/files.php?action=download&id=${fileId}`;
    }

    async deleteFile(fileId) {
        if (!confirm('Eliminare questo file?')) return;
        const response = await fetch(`api/files.php?id=${fileId}`, {
            method: 'DELETE',
            headers: {'X-CSRF-Token': window.app?.csrfToken || ''},
        });
        const result = await response.json();
        if (result.success) {
            window.app?.showToast('File eliminato', 'success');
            this.loadFolder(this.currentFolder);
        } else {
            window.app?.showToast(result.error || 'Impossibile eliminare', 'error');
        }
    }
}

window.fileManager = new FileManager();
