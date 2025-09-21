import app from './app.js';

class FileManager {
    constructor() {
        this.currentFolder = null;
        this.files = [];
        this.folders = [];
        this.viewMode = 'grid';
        this.folderMap = new Map();
        this.bound = false;
        this.init();
        document.addEventListener('collabora:auth-changed', () => this.init());
        document.addEventListener('collabora:tenant-changed', () => this.init());
        document.addEventListener('collabora:page-changed', (event) => {
            if (event.detail.page === 'files') {
                this.refresh();
            }
        });
    }

    init() {
        if (!app.user) {
            return;
        }
        if (!this.bound) {
            this.bindEvents();
            this.setupDragDrop();
            this.bound = true;
        }
        this.refresh();
    }

    async refresh() {
        if (!app.user) return;
        await Promise.all([this.loadFolderTree(), this.loadFiles(this.currentFolder)]);
    }

    bindEvents() {
        document.getElementById('upload-btn')?.addEventListener('click', () => {
            document.getElementById('file-input')?.click();
        });

        document.getElementById('file-input')?.addEventListener('change', (event) => {
            const files = event.target.files;
            if (files && files.length > 0) {
                this.uploadFiles(files);
                event.target.value = '';
            }
        });

        document.getElementById('new-folder-btn')?.addEventListener('click', () => {
            this.createFolder();
        });

        document.getElementById('view-toggle')?.addEventListener('click', () => {
            this.toggleView();
        });
    }

    setupDragDrop() {
        const dropZone = document.body;
        dropZone.addEventListener('dragover', (event) => {
            event.preventDefault();
            document.getElementById('drop-zone')?.classList.remove('hidden');
        });
        dropZone.addEventListener('dragleave', (event) => {
            if (event.target === dropZone) {
                document.getElementById('drop-zone')?.classList.add('hidden');
            }
        });
        dropZone.addEventListener('drop', (event) => {
            event.preventDefault();
            document.getElementById('drop-zone')?.classList.add('hidden');
            if (event.dataTransfer?.files?.length) {
                this.uploadFiles(event.dataTransfer.files);
            }
        });
    }

    async loadFolderTree() {
        try {
            const response = await app.authFetch(`api/folders.php?tree=1`);
            const data = await response.json();
            this.folders = data.folders || [];
            this.folderMap = new Map();
            const mapFolders = (items) => {
                items.forEach((folder) => {
                    this.folderMap.set(folder.id, folder);
                    if (folder.children) {
                        mapFolders(folder.children);
                    }
                });
            };
            mapFolders(this.folders);
            this.renderFolderTree();
            this.updateBreadcrumb();
        } catch (error) {
            console.error('Errore caricamento cartelle', error);
        }
    }

    renderFolderTree() {
        const treeContainer = document.getElementById('folder-tree');
        if (!treeContainer) return;
        const renderNodes = (nodes, depth = 0) => {
            return nodes
                .map((node) => {
                    const children = node.children ? renderNodes(node.children, depth + 1) : '';
                    const isActive = this.currentFolder === node.id;
                    return `
                        <div class="folder-node" data-id="${node.id}" style="margin-left:${depth * 12}px">
                            <button class="folder-btn ${isActive ? 'active' : ''}" data-id="${node.id}">📁 ${node.name}</button>
                            ${children}
                        </div>
                    `;
                })
                .join('');
        };
        treeContainer.innerHTML = `
            <div class="folder-node">
                <button class="folder-btn ${this.currentFolder ? '' : 'active'}" data-id="">🏠 Home</button>
            </div>
            ${renderNodes(this.folders)}
        `;
        treeContainer.querySelectorAll('.folder-btn').forEach((btn) => {
            btn.addEventListener('click', (event) => {
                const folderId = event.currentTarget.getAttribute('data-id');
                this.changeFolder(folderId ? parseInt(folderId, 10) : null);
            });
        });
    }

    async changeFolder(folderId) {
        this.currentFolder = folderId;
        await this.loadFiles(folderId);
        this.renderFolderTree();
        this.updateBreadcrumb();
    }

    async loadFiles(folderId) {
        try {
            const params = new URLSearchParams();
            if (folderId) {
                params.append('folder_id', folderId.toString());
            }
            const response = await app.authFetch(`api/files.php?${params.toString()}`);
            const data = await response.json();
            this.files = data.files || [];
            this.renderFiles();
        } catch (error) {
            console.error('Errore caricamento file', error);
        }
    }

    renderFiles() {
        const container = document.getElementById('file-grid');
        if (!container) return;
        container.className = this.viewMode === 'grid' ? 'file-grid' : 'file-list';
        if (!this.files.length) {
            container.innerHTML = '<div class="empty">Nessun file presente</div>';
            return;
        }
        container.innerHTML = this.files
            .map(
                (file) => `
                <div class="file-item" data-id="${file.id}">
                    <div class="file-icon">📄</div>
                    <div class="file-name">${file.original_name}</div>
                    <div class="file-size">${this.formatSize(file.size)}</div>
                    <div class="file-actions">
                        <button type="button" data-action="download" data-id="${file.id}">⬇</button>
                        <button type="button" data-action="delete" data-id="${file.id}">🗑</button>
                    </div>
                </div>
            `
            )
            .join('');
        container.querySelectorAll('button[data-action]')?.forEach((button) => {
            const action = button.getAttribute('data-action');
            const id = parseInt(button.getAttribute('data-id'), 10);
            if (action === 'download') {
                button.addEventListener('click', () => this.downloadFile(id));
            }
            if (action === 'delete') {
                button.addEventListener('click', () => this.deleteFile(id));
            }
        });
    }

    updateBreadcrumb() {
        const breadcrumb = document.getElementById('breadcrumb');
        if (!breadcrumb) return;
        const buildPath = [];
        if (this.currentFolder && this.folderMap.has(this.currentFolder)) {
            let folder = this.folderMap.get(this.currentFolder);
            while (folder) {
                buildPath.unshift(folder);
                folder = folder.parent_id ? this.folderMap.get(folder.parent_id) : null;
            }
        }
        breadcrumb.innerHTML = '<a href="#" data-folder="">Home</a>';
        buildPath.forEach((folder) => {
            const link = document.createElement('a');
            link.href = '#';
            link.dataset.folder = folder.id;
            link.textContent = folder.name;
            breadcrumb.appendChild(document.createTextNode(' / '));
            breadcrumb.appendChild(link);
            link.addEventListener('click', (event) => {
                event.preventDefault();
                this.changeFolder(folder.id);
            });
        });
    }

    toggleView() {
        this.viewMode = this.viewMode === 'grid' ? 'list' : 'grid';
        this.renderFiles();
    }

    formatSize(bytes) {
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        if (!bytes) return '0 B';
        const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), sizes.length - 1);
        const value = bytes / Math.pow(1024, i);
        return `${value.toFixed(value >= 10 || i === 0 ? 0 : 1)} ${sizes[i]}`;
    }

    async uploadFiles(fileList) {
        for (const file of fileList) {
            if (file.size > 524288000) {
                app.showToast(`File troppo grande: ${file.name}`, 'error');
                continue;
            }
            const formData = new FormData();
            formData.append('file', file);
            if (this.currentFolder) {
                formData.append('folder_id', this.currentFolder);
            }
            try {
                const response = await app.authFetch('api/files.php', {
                    method: 'POST',
                    body: formData,
                });
                const data = await response.json();
                if (data.success) {
                    app.showToast(`File ${file.name} caricato`, 'success');
                } else {
                    app.showToast(data.message || `Errore caricamento ${file.name}`, 'error');
                }
            } catch (error) {
                console.error(error);
                app.showToast(`Errore caricamento ${file.name}`, 'error');
            }
        }
        await this.loadFiles(this.currentFolder);
    }

    async createFolder() {
        const name = prompt('Nome della nuova cartella');
        if (!name) {
            return;
        }
        try {
            const response = await app.authFetch('api/folders.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    name,
                    parent_id: this.currentFolder,
                }),
            });
            const data = await response.json();
            if (data.success) {
                app.showToast('Cartella creata', 'success');
                this.currentFolder = data.folder?.id || this.currentFolder;
                await this.refresh();
            } else {
                app.showToast(data.message || 'Errore creazione cartella', 'error');
            }
        } catch (error) {
            console.error(error);
            app.showToast('Errore creazione cartella', 'error');
        }
    }

    async deleteFile(fileId) {
        if (!confirm('Eliminare questo file?')) return;
        try {
            const response = await app.authFetch(`api/files.php?id=${fileId}`, {
                method: 'DELETE',
            });
            const data = await response.json();
            if (data.success) {
                app.showToast('File eliminato', 'success');
                await this.loadFiles(this.currentFolder);
            } else {
                app.showToast(data.message || 'Errore eliminazione file', 'error');
            }
        } catch (error) {
            console.error(error);
            app.showToast('Errore eliminazione file', 'error');
        }
    }

    downloadFile(fileId) {
        window.location.href = `api/files.php?action=download&id=${fileId}`;
    }
}

export const fileManager = new FileManager();
export default fileManager;
