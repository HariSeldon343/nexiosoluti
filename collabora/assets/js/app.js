/**
 * Nexio File Manager - Main Application
 * Core application logic and initialization
 */

// ================================
// API MODULE IMPORTS
// ================================
// Load all API modules in the correct order
(function loadApiModules() {
  const scripts = [
    'api-config.js',
    'modules/calendar-api.js',
    'modules/task-api.js',
    'modules/chat-api.js',
    'modules/sharing-api.js',
    'modules/dashboard-api.js'
  ];

  // Get the base path for scripts
  const currentScript = document.currentScript;
  const basePath = currentScript ?
    currentScript.src.substring(0, currentScript.src.lastIndexOf('/') + 1) :
    '/assets/js/';

  // Load scripts dynamically
  scripts.forEach(script => {
    const scriptElement = document.createElement('script');
    scriptElement.src = basePath + script;
    scriptElement.async = false; // Ensure order
    document.head.appendChild(scriptElement);
  });
})();

// ================================
// APPLICATION STATE
// ================================
const AppState = {
  currentPath: '/',
  currentFolder: null,
  selectedFiles: new Set(),
  viewMode: 'grid', // 'grid' or 'list'
  theme: 'light',
  user: null,
  tenant: null,
  csrfToken: null,
  uploadQueue: [],
  contextMenu: null,
  dragCounter: 0,
  searchQuery: '',
  sortBy: 'name',
  sortOrder: 'asc',
  // API module references
  apis: {
    config: null,
    calendar: null,
    task: null,
    chat: null,
    sharing: null,
    dashboard: null
  }
};

// ================================
// APPLICATION INITIALIZATION
// ================================
class FileManagerApp {
  constructor() {
    this.state = AppState;
    this.fileManager = null;
    this.components = null;
    this.initialized = false;
  }

  async init() {
    if (this.initialized) return;

    try {
      // Wait for API modules to load
      await this.waitForApiModules();

      // Initialize API module references
      this.initializeApiModules();

      // Initialize CSRF token
      this.state.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

      // Initialize modules
      const { FileManager } = await import('./filemanager.js');
      const { Components } = await import('./components.js');

      this.fileManager = new FileManager(this.state);
      this.components = new Components(this.state);

      // Initialize UI components
      this.initializeTheme();
      this.initializeSidebar();
      this.initializeEventListeners();
      this.initializeDragDrop();
      this.initializeKeyboardShortcuts();
      this.initializeResizeObserver();

      // Initialize real-time features
      this.initializeRealtimeFeatures();

      // Load initial data
      await this.loadUserData();
      await this.loadFolderTree();
      await this.loadFiles();
      await this.loadDashboard();

      // Mark as initialized
      this.initialized = true;

      // Show welcome message
      this.components.showToast('success', 'Benvenuto', 'File Manager caricato con successo');

    } catch (error) {
      console.error('Initialization error:', error);
      this.components?.showToast('error', 'Errore', 'Impossibile inizializzare l\'applicazione');
    }
  }

  /**
   * Wait for API modules to be available
   */
  async waitForApiModules() {
    const maxAttempts = 50; // 5 seconds timeout
    let attempts = 0;

    while (attempts < maxAttempts) {
      if (window.APIConfig &&
          window.CalendarAPI &&
          window.TaskAPI &&
          window.ChatAPI &&
          window.SharingAPI &&
          window.DashboardAPI) {
        return true;
      }
      await new Promise(resolve => setTimeout(resolve, 100));
      attempts++;
    }

    throw new Error('API modules failed to load');
  }

  /**
   * Initialize API module references
   */
  initializeApiModules() {
    this.state.apis = {
      config: window.APIConfig,
      calendar: window.CalendarAPI,
      task: window.TaskAPI,
      chat: window.ChatAPI,
      sharing: window.SharingAPI,
      dashboard: window.DashboardAPI
    };

    // Log API initialization
    console.log('API Modules initialized:', {
      config: !!this.state.apis.config,
      calendar: !!this.state.apis.calendar,
      task: !!this.state.apis.task,
      chat: !!this.state.apis.chat,
      sharing: !!this.state.apis.sharing,
      dashboard: !!this.state.apis.dashboard
    });
  }

  /**
   * Initialize real-time features
   */
  initializeRealtimeFeatures() {
    // Start notification polling
    if (this.state.apis.dashboard) {
      this.state.apis.dashboard.startNotificationPolling((notifications) => {
        this.handleNewNotifications(notifications);
      });
    }

    // Start presence heartbeat for chat
    if (this.state.apis.chat) {
      this.state.apis.chat.startPresenceHeartbeat();
    }

    // Subscribe to dashboard metrics refresh
    if (this.state.apis.dashboard) {
      this.state.apis.dashboard.startMetricsRefresh((metrics) => {
        this.updateDashboardMetrics(metrics);
      }, ['storage', 'files', 'users'], 30000);
    }
  }

  // ================================
  // THEME MANAGEMENT
  // ================================
  initializeTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    this.setTheme(savedTheme);

    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
      themeToggle.addEventListener('click', () => {
        const newTheme = this.state.theme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
      });
    }
  }

  setTheme(theme) {
    this.state.theme = theme;
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);

    const themeIcon = document.querySelector('#themeToggle svg');
    if (themeIcon) {
      themeIcon.innerHTML = theme === 'light'
        ? this.getIcon('moon')
        : this.getIcon('sun');
    }
  }

  // ================================
  // SIDEBAR MANAGEMENT
  // ================================
  initializeSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');

    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', () => {
        sidebar?.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', sidebar?.classList.contains('collapsed'));
      });
    }

    if (mobileMenuToggle) {
      mobileMenuToggle.addEventListener('click', () => {
        sidebar?.classList.toggle('mobile-open');
        document.querySelector('.sidebar-backdrop')?.classList.toggle('active');
      });
    }

    // Restore sidebar state
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed) {
      sidebar?.classList.add('collapsed');
    }

    // Close mobile sidebar on backdrop click
    document.querySelector('.sidebar-backdrop')?.addEventListener('click', () => {
      sidebar?.classList.remove('mobile-open');
      document.querySelector('.sidebar-backdrop')?.classList.remove('active');
    });
  }

  // ================================
  // EVENT LISTENERS
  // ================================
  initializeEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
      let searchTimeout;
      searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          this.handleSearch(e.target.value);
        }, 300);
      });
    }

    // View mode toggle
    document.querySelectorAll('.view-toggle-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const mode = e.currentTarget.dataset.view;
        this.setViewMode(mode);
      });
    });

    // File selection
    document.addEventListener('click', (e) => {
      if (e.target.closest('.file-item')) {
        this.handleFileClick(e);
      } else if (!e.target.closest('.context-menu')) {
        this.clearSelection();
      }
    });

    // Context menu
    document.addEventListener('contextmenu', (e) => {
      if (e.target.closest('.file-item') || e.target.closest('.file-explorer')) {
        e.preventDefault();
        this.showContextMenu(e);
      }
    });

    // Hide context menu on click
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.context-menu')) {
        this.hideContextMenu();
      }
    });

    // Breadcrumb navigation
    document.addEventListener('click', (e) => {
      if (e.target.closest('.breadcrumb-item')) {
        const path = e.target.closest('.breadcrumb-item').dataset.path;
        this.navigateToPath(path);
      }
    });

    // Button actions
    document.getElementById('btnUpload')?.addEventListener('click', () => {
      this.components.showUploadModal();
    });

    document.getElementById('btnNewFolder')?.addEventListener('click', () => {
      this.components.showNewFolderModal();
    });

    document.getElementById('btnRefresh')?.addEventListener('click', () => {
      this.loadFiles();
    });

    document.getElementById('btnDelete')?.addEventListener('click', () => {
      this.deleteSelectedFiles();
    });

    // User menu
    document.querySelector('.user-avatar')?.addEventListener('click', (e) => {
      this.components.toggleDropdown(e.currentTarget);
    });
  }

  // ================================
  // DRAG & DROP
  // ================================
  initializeDragDrop() {
    const dropZone = document.querySelector('.file-explorer');
    const dropOverlay = document.querySelector('.drop-zone');

    if (!dropZone || !dropOverlay) return;

    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, this.preventDefaults.bind(this), false);
      document.body.addEventListener(eventName, this.preventDefaults.bind(this), false);
    });

    // Track drag enter/leave
    dropZone.addEventListener('dragenter', (e) => {
      this.state.dragCounter++;
      if (this.state.dragCounter === 1) {
        dropOverlay.classList.add('active');
      }
    });

    dropZone.addEventListener('dragleave', (e) => {
      this.state.dragCounter--;
      if (this.state.dragCounter === 0) {
        dropOverlay.classList.remove('active');
      }
    });

    // Handle drop
    dropZone.addEventListener('drop', async (e) => {
      this.state.dragCounter = 0;
      dropOverlay.classList.remove('active');

      const files = Array.from(e.dataTransfer.files);
      if (files.length > 0) {
        await this.handleFileUpload(files);
      }
    });
  }

  preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  }

  // ================================
  // KEYBOARD SHORTCUTS
  // ================================
  initializeKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
      // Ctrl/Cmd + A - Select all
      if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
        e.preventDefault();
        this.selectAllFiles();
      }

      // Ctrl/Cmd + D - Deselect all
      if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
        e.preventDefault();
        this.clearSelection();
      }

      // Delete key - Delete selected
      if (e.key === 'Delete' && this.state.selectedFiles.size > 0) {
        e.preventDefault();
        this.deleteSelectedFiles();
      }

      // F2 - Rename
      if (e.key === 'F2' && this.state.selectedFiles.size === 1) {
        e.preventDefault();
        const fileId = Array.from(this.state.selectedFiles)[0];
        this.renameFile(fileId);
      }

      // Ctrl/Cmd + F - Focus search
      if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('searchInput')?.focus();
      }

      // Escape - Clear selection or close modals
      if (e.key === 'Escape') {
        if (document.querySelector('.modal-backdrop.active')) {
          this.components.closeAllModals();
        } else {
          this.clearSelection();
        }
      }
    });
  }

  // ================================
  // RESIZE OBSERVER
  // ================================
  initializeResizeObserver() {
    const fileExplorer = document.querySelector('.file-explorer');
    if (!fileExplorer) return;

    const resizeObserver = new ResizeObserver(entries => {
      for (let entry of entries) {
        const width = entry.contentRect.width;
        if (width < 600) {
          fileExplorer.classList.add('compact');
        } else {
          fileExplorer.classList.remove('compact');
        }
      }
    });

    resizeObserver.observe(fileExplorer);
  }

  // ================================
  // DATA LOADING
  // ================================
  async loadUserData() {
    try {
      const userData = await this.fileManager.getUserData();
      this.state.user = userData.user;
      this.state.tenant = userData.tenant;
      this.updateUserInterface();
    } catch (error) {
      console.error('Error loading user data:', error);
    }
  }

  async loadFolderTree() {
    try {
      const tree = await this.fileManager.getFolderTree();
      this.renderFolderTree(tree);
    } catch (error) {
      console.error('Error loading folder tree:', error);
    }
  }

  async loadFiles(folderId = null) {
    try {
      this.showLoadingState();
      const files = await this.fileManager.getFiles(folderId || this.state.currentFolder);
      this.renderFiles(files);
      this.updateBreadcrumb();
      this.hideLoadingState();
    } catch (error) {
      console.error('Error loading files:', error);
      this.hideLoadingState();
      this.components.showToast('error', 'Errore', 'Impossibile caricare i file');
    }
  }

  // ================================
  // FILE OPERATIONS
  // ================================
  async handleFileUpload(files) {
    try {
      const uploadProgress = this.components.showUploadProgress(files);
      const results = await this.fileManager.uploadFiles(files, this.state.currentFolder, uploadProgress);

      if (results.success) {
        await this.loadFiles();
        this.components.showToast('success', 'Upload completato', `${results.uploaded} file caricati con successo`);
      } else {
        this.components.showToast('error', 'Errore upload', results.error);
      }
    } catch (error) {
      console.error('Upload error:', error);
      this.components.showToast('error', 'Errore', 'Impossibile caricare i file');
    }
  }

  async deleteSelectedFiles() {
    const fileIds = Array.from(this.state.selectedFiles);
    if (fileIds.length === 0) return;

    const confirmed = await this.components.showConfirmDialog(
      'Elimina file',
      `Sei sicuro di voler eliminare ${fileIds.length} file?`
    );

    if (!confirmed) return;

    try {
      const results = await this.fileManager.deleteFiles(fileIds);
      if (results.success) {
        this.clearSelection();
        await this.loadFiles();
        this.components.showToast('success', 'File eliminati', `${fileIds.length} file eliminati con successo`);
      } else {
        this.components.showToast('error', 'Errore', results.error);
      }
    } catch (error) {
      console.error('Delete error:', error);
      this.components.showToast('error', 'Errore', 'Impossibile eliminare i file');
    }
  }

  async renameFile(fileId) {
    const file = this.getFileById(fileId);
    if (!file) return;

    const newName = await this.components.showPromptDialog(
      'Rinomina file',
      'Inserisci il nuovo nome:',
      file.name
    );

    if (!newName || newName === file.name) return;

    try {
      const result = await this.fileManager.renameFile(fileId, newName);
      if (result.success) {
        await this.loadFiles();
        this.components.showToast('success', 'File rinominato', 'Il file è stato rinominato con successo');
      } else {
        this.components.showToast('error', 'Errore', result.error);
      }
    } catch (error) {
      console.error('Rename error:', error);
      this.components.showToast('error', 'Errore', 'Impossibile rinominare il file');
    }
  }

  // ================================
  // UI RENDERING
  // ================================
  renderFolderTree(tree) {
    const container = document.querySelector('.folder-tree');
    if (!container) return;

    container.innerHTML = this.buildFolderTreeHTML(tree);
    this.attachFolderClickHandlers();
  }

  buildFolderTreeHTML(folders, level = 0) {
    let html = '';
    folders.forEach(folder => {
      const padding = level * 16;
      html += `
        <div class="nav-item folder-item" data-folder-id="${folder.id}" style="padding-left: ${16 + padding}px;">
          <svg class="nav-item-icon" viewBox="0 0 20 20" fill="currentColor">
            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
          </svg>
          <span class="nav-item-text">${this.escapeHtml(folder.name)}</span>
          ${folder.file_count > 0 ? `<span class="nav-item-badge">${folder.file_count}</span>` : ''}
        </div>
      `;

      if (folder.children && folder.children.length > 0) {
        html += this.buildFolderTreeHTML(folder.children, level + 1);
      }
    });
    return html;
  }

  attachFolderClickHandlers() {
    document.querySelectorAll('.folder-item').forEach(item => {
      item.addEventListener('click', (e) => {
        const folderId = e.currentTarget.dataset.folderId;
        this.navigateToFolder(folderId);
      });
    });
  }

  renderFiles(files) {
    const container = document.querySelector('.file-container');
    if (!container) return;

    if (files.length === 0) {
      container.innerHTML = this.getEmptyStateHTML();
      return;
    }

    const viewMode = this.state.viewMode;
    container.className = `file-container file-${viewMode}`;

    if (viewMode === 'grid') {
      container.innerHTML = this.buildFileGridHTML(files);
    } else {
      container.innerHTML = this.buildFileListHTML(files);
    }

    this.attachFileHandlers();
  }

  buildFileGridHTML(files) {
    return files.map(file => `
      <div class="file-item" data-file-id="${file.id}" data-file-name="${this.escapeHtml(file.name)}">
        <input type="checkbox" class="file-checkbox" data-file-id="${file.id}">
        <div class="file-icon">
          ${this.getFileIcon(file.extension)}
        </div>
        <div class="file-name">${this.escapeHtml(file.name)}</div>
        <div class="file-meta">${this.formatFileSize(file.size)}</div>
      </div>
    `).join('');
  }

  buildFileListHTML(files) {
    const header = `
      <div class="file-list">
        <div class="file-list-header">
          <div></div>
          <div>Nome</div>
          <div>Dimensione</div>
          <div>Tipo</div>
          <div>Modificato</div>
          <div>Azioni</div>
        </div>
    `;

    const items = files.map(file => `
      <div class="file-list-item" data-file-id="${file.id}" data-file-name="${this.escapeHtml(file.name)}">
        <div>
          <input type="checkbox" class="file-checkbox" data-file-id="${file.id}">
        </div>
        <div class="file-list-name">
          <span class="file-list-icon">${this.getFileIcon(file.extension)}</span>
          <span>${this.escapeHtml(file.name)}</span>
        </div>
        <div>${this.formatFileSize(file.size)}</div>
        <div>${file.extension.toUpperCase()}</div>
        <div>${this.formatDate(file.updated_at)}</div>
        <div>
          <button class="btn btn-icon btn-ghost" onclick="app.showFileActions(event, '${file.id}')">
            ${this.getIcon('dots-vertical')}
          </button>
        </div>
      </div>
    `).join('');

    return header + items + '</div>';
  }

  attachFileHandlers() {
    document.querySelectorAll('.file-item, .file-list-item').forEach(item => {
      // Double click to open
      item.addEventListener('dblclick', (e) => {
        const fileId = e.currentTarget.dataset.fileId;
        this.openFile(fileId);
      });

      // Checkbox change
      const checkbox = item.querySelector('.file-checkbox');
      if (checkbox) {
        checkbox.addEventListener('change', (e) => {
          const fileId = e.target.dataset.fileId;
          if (e.target.checked) {
            this.state.selectedFiles.add(fileId);
            item.classList.add('selected');
          } else {
            this.state.selectedFiles.delete(fileId);
            item.classList.remove('selected');
          }
          this.updateSelectionUI();
        });
      }
    });
  }

  // ================================
  // UI HELPERS
  // ================================
  updateBreadcrumb() {
    const container = document.querySelector('.breadcrumb');
    if (!container) return;

    const path = this.state.currentPath.split('/').filter(p => p);
    let html = `
      <span class="breadcrumb-item" data-path="/">
        ${this.getIcon('home')}
      </span>
    `;

    let currentPath = '';
    path.forEach((segment, index) => {
      currentPath += '/' + segment;
      const isLast = index === path.length - 1;

      html += `
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-${isLast ? 'current' : 'item'}" data-path="${currentPath}">
          ${this.escapeHtml(segment)}
        </span>
      `;
    });

    container.innerHTML = html;
  }

  updateUserInterface() {
    // Update user avatar
    const avatar = document.querySelector('.user-avatar');
    if (avatar && this.state.user) {
      const initials = this.state.user.full_name
        .split(' ')
        .map(n => n[0])
        .join('')
        .toUpperCase();
      avatar.textContent = initials;
    }

    // Update storage indicator
    this.updateStorageIndicator();
  }

  updateStorageIndicator() {
    const fill = document.querySelector('.storage-bar-fill');
    const percent = document.querySelector('.storage-percent');
    const details = document.querySelector('.storage-details');

    if (this.state.tenant) {
      const usedPercent = (this.state.tenant.storage_used / this.state.tenant.storage_limit) * 100;

      if (fill) fill.style.width = `${Math.min(usedPercent, 100)}%`;
      if (percent) percent.textContent = `${Math.round(usedPercent)}%`;
      if (details) {
        details.innerHTML = `
          <span>${this.formatFileSize(this.state.tenant.storage_used)}</span>
          <span>${this.formatFileSize(this.state.tenant.storage_limit)}</span>
        `;
      }
    }
  }

  updateSelectionUI() {
    const count = this.state.selectedFiles.size;
    const deleteBtn = document.getElementById('btnDelete');

    if (deleteBtn) {
      deleteBtn.disabled = count === 0;
    }

    // Update selection count in toolbar
    const selectionInfo = document.querySelector('.selection-info');
    if (selectionInfo) {
      if (count > 0) {
        selectionInfo.textContent = `${count} file selezionati`;
        selectionInfo.style.display = 'block';
      } else {
        selectionInfo.style.display = 'none';
      }
    }
  }

  showLoadingState() {
    const container = document.querySelector('.file-container');
    if (!container) return;

    container.innerHTML = `
      <div class="loading-overlay">
        <div class="spinner"></div>
      </div>
    `;
  }

  hideLoadingState() {
    const overlay = document.querySelector('.loading-overlay');
    if (overlay) {
      overlay.remove();
    }
  }

  getEmptyStateHTML() {
    return `
      <div class="empty-state">
        <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
        </svg>
        <h3 class="empty-state-title">Nessun file presente</h3>
        <p class="empty-state-description">
          Trascina i file qui o clicca sul pulsante "Carica" per iniziare
        </p>
        <button class="btn btn-primary" onclick="app.components.showUploadModal()">
          ${this.getIcon('upload')}
          Carica file
        </button>
      </div>
    `;
  }

  // ================================
  // CONTEXT MENU
  // ================================
  showContextMenu(e) {
    const menu = document.querySelector('.context-menu');
    if (!menu) return;

    const fileItem = e.target.closest('.file-item, .file-list-item');
    const fileId = fileItem?.dataset.fileId;

    let menuHTML = '';

    if (fileId) {
      // File context menu
      menuHTML = `
        <div class="context-menu-item" onclick="app.openFile('${fileId}')">
          ${this.getIcon('external-link')} Apri
        </div>
        <div class="context-menu-item" onclick="app.downloadFile('${fileId}')">
          ${this.getIcon('download')} Scarica
        </div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item" onclick="app.renameFile('${fileId}')">
          ${this.getIcon('edit')} Rinomina
        </div>
        <div class="context-menu-item" onclick="app.moveFile('${fileId}')">
          ${this.getIcon('folder')} Sposta
        </div>
        <div class="context-menu-item" onclick="app.copyFile('${fileId}')">
          ${this.getIcon('copy')} Copia
        </div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item" onclick="app.shareFile('${fileId}')">
          ${this.getIcon('share')} Condividi
        </div>
        <div class="context-menu-item" onclick="app.showFileInfo('${fileId}')">
          ${this.getIcon('info')} Informazioni
        </div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item danger" onclick="app.deleteFile('${fileId}')">
          ${this.getIcon('trash')} Elimina
        </div>
      `;
    } else {
      // General context menu
      menuHTML = `
        <div class="context-menu-item" onclick="app.components.showUploadModal()">
          ${this.getIcon('upload')} Carica file
        </div>
        <div class="context-menu-item" onclick="app.components.showNewFolderModal()">
          ${this.getIcon('folder-plus')} Nuova cartella
        </div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item" onclick="app.loadFiles()">
          ${this.getIcon('refresh')} Aggiorna
        </div>
        <div class="context-menu-item" onclick="app.selectAllFiles()">
          ${this.getIcon('check-all')} Seleziona tutto
        </div>
      `;
    }

    menu.innerHTML = menuHTML;
    menu.style.left = `${e.pageX}px`;
    menu.style.top = `${e.pageY}px`;
    menu.classList.add('active');

    // Adjust position if menu goes off screen
    setTimeout(() => {
      const rect = menu.getBoundingClientRect();
      if (rect.right > window.innerWidth) {
        menu.style.left = `${e.pageX - rect.width}px`;
      }
      if (rect.bottom > window.innerHeight) {
        menu.style.top = `${e.pageY - rect.height}px`;
      }
    }, 0);
  }

  hideContextMenu() {
    document.querySelector('.context-menu')?.classList.remove('active');
  }

  // ================================
  // UTILITY FUNCTIONS
  // ================================
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;

    if (diff < 86400000) { // Less than 24 hours
      const hours = Math.floor(diff / 3600000);
      if (hours < 1) return 'Ora';
      return `${hours} ${hours === 1 ? 'ora' : 'ore'} fa`;
    } else if (diff < 604800000) { // Less than 7 days
      const days = Math.floor(diff / 86400000);
      return `${days} ${days === 1 ? 'giorno' : 'giorni'} fa`;
    } else {
      return date.toLocaleDateString('it-IT');
    }
  }

  getFileIcon(extension) {
    const icons = {
      pdf: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18.5,9H13V3.5L18.5,9M6,20V4H11V10H18V20H6Z"/></svg>',
      doc: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>',
      docx: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>',
      xls: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20M8,15.5V17H16V15.5H8M8,11.5V13H16V11.5H8Z"/></svg>',
      xlsx: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20M8,15.5V17H16V15.5H8M8,11.5V13H16V11.5H8Z"/></svg>',
      jpg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
      jpeg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
      png: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
      gif: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
      mp3: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M13,13H11V18A2,2 0 0,1 9,20A2,2 0 0,1 7,18A2,2 0 0,1 9,16C9.4,16 9.7,16.1 10,16.3V11H13V13M13,9V3.5L18.5,9H13Z"/></svg>',
      mp4: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M13,9V3.5L18.5,9H13M10,11L14,14L10,17V11Z"/></svg>',
      zip: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14,17H12V15H10V13H12V15H14M14,9H12V11H14V13H12V11H10V9H12V7H10V5H12V7H14M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3Z"/></svg>',
      default: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M13,9V3.5L18.5,9M6,2C4.89,2 4,2.89 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2H6Z"/></svg>'
    };

    return icons[extension?.toLowerCase()] || icons.default;
  }

  getIcon(name) {
    const icons = {
      'home': '<svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>',
      'folder': '<svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>',
      'upload': '<svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>',
      'download': '<svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 012 0v7.586l1.293-1.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>',
      'trash': '<svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
      'refresh': '<svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/></svg>',
      'moon': '<svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>',
      'sun': '<svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/></svg>'
    };

    return icons[name] || '';
  }

  // ================================
  // PUBLIC API
  // ================================
  handleSearch(query) {
    this.state.searchQuery = query;
    if (query) {
      this.fileManager.searchFiles(query).then(files => {
        this.renderFiles(files);
      });
    } else {
      this.loadFiles();
    }
  }

  setViewMode(mode) {
    this.state.viewMode = mode;
    document.querySelectorAll('.view-toggle-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.view === mode);
    });
    this.loadFiles();
    localStorage.setItem('viewMode', mode);
  }

  navigateToFolder(folderId) {
    this.state.currentFolder = folderId;
    this.loadFiles(folderId);

    // Update active folder in tree
    document.querySelectorAll('.folder-item').forEach(item => {
      item.classList.toggle('active', item.dataset.folderId === folderId);
    });
  }

  navigateToPath(path) {
    this.state.currentPath = path;
    // TODO: Convert path to folder ID and navigate
    this.loadFiles();
  }

  handleFileClick(e) {
    const item = e.target.closest('.file-item, .file-list-item');
    if (!item) return;

    const fileId = item.dataset.fileId;
    const checkbox = item.querySelector('.file-checkbox');

    if (e.target === checkbox) return; // Let checkbox handle itself

    if (e.ctrlKey || e.metaKey) {
      // Toggle selection
      checkbox.checked = !checkbox.checked;
      checkbox.dispatchEvent(new Event('change'));
    } else if (e.shiftKey && this.lastSelectedFile) {
      // Range selection
      this.selectRange(this.lastSelectedFile, fileId);
    } else {
      // Single selection
      this.clearSelection();
      checkbox.checked = true;
      checkbox.dispatchEvent(new Event('change'));
    }

    this.lastSelectedFile = fileId;
  }

  selectAllFiles() {
    document.querySelectorAll('.file-checkbox').forEach(checkbox => {
      checkbox.checked = true;
      checkbox.dispatchEvent(new Event('change'));
    });
  }

  clearSelection() {
    document.querySelectorAll('.file-checkbox').forEach(checkbox => {
      checkbox.checked = false;
      checkbox.dispatchEvent(new Event('change'));
    });
    this.state.selectedFiles.clear();
    this.updateSelectionUI();
  }

  selectRange(fromId, toId) {
    const items = Array.from(document.querySelectorAll('[data-file-id]'));
    const fromIndex = items.findIndex(i => i.dataset.fileId === fromId);
    const toIndex = items.findIndex(i => i.dataset.fileId === toId);

    const start = Math.min(fromIndex, toIndex);
    const end = Math.max(fromIndex, toIndex);

    for (let i = start; i <= end; i++) {
      const checkbox = items[i].querySelector('.file-checkbox');
      if (checkbox) {
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change'));
      }
    }
  }

  async openFile(fileId) {
    const file = this.getFileById(fileId);
    if (!file) return;

    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    const pdfExtensions = ['pdf'];

    if (imageExtensions.includes(file.extension.toLowerCase())) {
      this.components.showImagePreview(file);
    } else if (pdfExtensions.includes(file.extension.toLowerCase())) {
      this.components.showPdfPreview(file);
    } else {
      this.downloadFile(fileId);
    }
  }

  downloadFile(fileId) {
    this.fileManager.downloadFile(fileId);
  }

  async deleteFile(fileId) {
    this.state.selectedFiles.clear();
    this.state.selectedFiles.add(fileId);
    await this.deleteSelectedFiles();
  }

  async moveFile(fileId) {
    const targetFolder = await this.components.showFolderSelectModal();
    if (!targetFolder) return;

    try {
      const result = await this.fileManager.moveFile(fileId, targetFolder);
      if (result.success) {
        await this.loadFiles();
        this.components.showToast('success', 'File spostato', 'Il file è stato spostato con successo');
      }
    } catch (error) {
      this.components.showToast('error', 'Errore', 'Impossibile spostare il file');
    }
  }

  async copyFile(fileId) {
    try {
      const result = await this.fileManager.copyFile(fileId);
      if (result.success) {
        await this.loadFiles();
        this.components.showToast('success', 'File copiato', 'Il file è stato copiato con successo');
      }
    } catch (error) {
      this.components.showToast('error', 'Errore', 'Impossibile copiare il file');
    }
  }

  async shareFile(fileId) {
    try {
      // Use the Sharing API to create a share link
      const shareData = await this.state.apis.sharing.createShareLink({
        item_id: fileId,
        item_type: 'file',
        permission: 'view',
        expires_at: null // No expiration by default
      });

      if (shareData.success) {
        this.components.showShareModal(shareData.link);
      }
    } catch (error) {
      console.error('Share error:', error);
      this.components.showToast('error', 'Errore', 'Impossibile creare il link di condivisione');
    }
  }

  async showFileInfo(fileId) {
    const file = this.getFileById(fileId);
    if (file) {
      this.components.showFileInfoModal(file);
    }
  }

  showFileActions(event, fileId) {
    event.stopPropagation();
    const rect = event.currentTarget.getBoundingClientRect();
    this.showContextMenu({
      pageX: rect.left,
      pageY: rect.bottom,
      target: document.querySelector(`[data-file-id="${fileId}"]`)
    });
  }

  getFileById(fileId) {
    // This would normally query from the current files list
    // For now, return mock data
    return {
      id: fileId,
      name: 'document.pdf',
      extension: 'pdf',
      size: 1024000,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    };
  }

  // ================================
  // API INTEGRATION METHODS
  // ================================

  /**
   * Load dashboard data
   */
  async loadDashboard() {
    try {
      if (!this.state.apis.dashboard) return;

      const dashboardData = await this.state.apis.dashboard.getDashboard();
      if (dashboardData.success) {
        this.renderDashboardWidgets(dashboardData.widgets);
      }
    } catch (error) {
      console.error('Dashboard load error:', error);
    }
  }

  /**
   * Handle new notifications
   */
  handleNewNotifications(notifications) {
    if (!notifications || notifications.length === 0) return;

    notifications.forEach(notification => {
      this.components.showToast(
        notification.type || 'info',
        notification.title || 'Notifica',
        notification.message
      );
    });

    // Update notification badge
    this.updateNotificationBadge(notifications.length);
  }

  /**
   * Update dashboard metrics
   */
  updateDashboardMetrics(metrics) {
    // Update storage indicator
    if (metrics.storage) {
      this.state.tenant = {
        ...this.state.tenant,
        storage_used: metrics.storage.used,
        storage_limit: metrics.storage.limit
      };
      this.updateStorageIndicator();
    }

    // Update file count
    if (metrics.files) {
      const fileCountElement = document.querySelector('.file-count');
      if (fileCountElement) {
        fileCountElement.textContent = metrics.files.total;
      }
    }
  }

  /**
   * Render dashboard widgets
   */
  renderDashboardWidgets(widgets) {
    const container = document.querySelector('.dashboard-widgets');
    if (!container || !widgets) return;

    // Clear existing widgets
    container.innerHTML = '';

    widgets.forEach(widget => {
      const widgetElement = this.createWidgetElement(widget);
      container.appendChild(widgetElement);
    });
  }

  /**
   * Create widget element
   */
  createWidgetElement(widget) {
    const div = document.createElement('div');
    div.className = `widget widget-${widget.type}`;
    div.dataset.widgetId = widget.id;

    switch (widget.type) {
      case 'stats':
        div.innerHTML = this.createStatsWidget(widget);
        break;
      case 'activity':
        div.innerHTML = this.createActivityWidget(widget);
        break;
      case 'calendar':
        div.innerHTML = this.createCalendarWidget(widget);
        break;
      case 'tasks':
        div.innerHTML = this.createTasksWidget(widget);
        break;
      default:
        div.innerHTML = `<div class="widget-content">Unknown widget type: ${widget.type}</div>`;
    }

    return div;
  }

  /**
   * Create stats widget HTML
   */
  createStatsWidget(widget) {
    return `
      <div class="widget-header">
        <h3>${widget.config.title || 'Statistics'}</h3>
      </div>
      <div class="widget-content">
        <div class="stats-grid">
          ${widget.data.stats ? widget.data.stats.map(stat => `
            <div class="stat-item">
              <div class="stat-value">${stat.value}</div>
              <div class="stat-label">${stat.label}</div>
            </div>
          `).join('') : 'Loading...'}
        </div>
      </div>
    `;
  }

  /**
   * Create activity widget HTML
   */
  createActivityWidget(widget) {
    return `
      <div class="widget-header">
        <h3>${widget.config.title || 'Recent Activity'}</h3>
      </div>
      <div class="widget-content">
        <div class="activity-list">
          ${widget.data.activities ? widget.data.activities.map(activity => `
            <div class="activity-item">
              <div class="activity-icon">${this.getActivityIcon(activity.type)}</div>
              <div class="activity-details">
                <div class="activity-message">${activity.message}</div>
                <div class="activity-time">${this.formatDate(activity.timestamp)}</div>
              </div>
            </div>
          `).join('') : 'No recent activity'}
        </div>
      </div>
    `;
  }

  /**
   * Create calendar widget HTML
   */
  createCalendarWidget(widget) {
    return `
      <div class="widget-header">
        <h3>${widget.config.title || 'Calendar'}</h3>
      </div>
      <div class="widget-content">
        <div class="calendar-mini" id="calendar-widget-${widget.id}">
          Loading calendar...
        </div>
      </div>
    `;
  }

  /**
   * Create tasks widget HTML
   */
  createTasksWidget(widget) {
    return `
      <div class="widget-header">
        <h3>${widget.config.title || 'Tasks'}</h3>
      </div>
      <div class="widget-content">
        <div class="tasks-list">
          ${widget.data.tasks ? widget.data.tasks.map(task => `
            <div class="task-item" data-task-id="${task.id}">
              <input type="checkbox" ${task.completed ? 'checked' : ''}>
              <span class="task-title">${task.title}</span>
              <span class="task-priority priority-${task.priority}">${task.priority}</span>
            </div>
          `).join('') : 'No tasks'}
        </div>
      </div>
    `;
  }

  /**
   * Get activity icon based on type
   */
  getActivityIcon(type) {
    const icons = {
      upload: this.getIcon('upload'),
      download: this.getIcon('download'),
      delete: this.getIcon('trash'),
      share: this.getIcon('share'),
      edit: this.getIcon('edit'),
      folder: this.getIcon('folder')
    };

    return icons[type] || this.getIcon('file');
  }

  /**
   * Update notification badge
   */
  updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
      badge.textContent = count > 9 ? '9+' : count;
      badge.style.display = count > 0 ? 'block' : 'none';
    }
  }

  /**
   * Load calendar events for widget
   */
  async loadCalendarEvents() {
    try {
      if (!this.state.apis.calendar) return;

      const events = await this.state.apis.calendar.getEvents({
        start: new Date().toISOString(),
        end: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
        view: 'month'
      });

      if (events.success) {
        this.renderCalendarEvents(events.events);
      }
    } catch (error) {
      console.error('Calendar load error:', error);
    }
  }

  /**
   * Load tasks for widget
   */
  async loadTasks() {
    try {
      if (!this.state.apis.task) return;

      const tasks = await this.state.apis.task.getTasks({
        status: 'pending',
        limit: 10,
        sort: 'due_date',
        order: 'asc'
      });

      if (tasks.success) {
        this.renderTasksList(tasks.tasks);
      }
    } catch (error) {
      console.error('Tasks load error:', error);
    }
  }

  /**
   * Clean up on page unload
   */
  cleanup() {
    // Stop all polling and intervals
    if (this.state.apis.dashboard) {
      this.state.apis.dashboard.cleanup();
    }
    if (this.state.apis.chat) {
      this.state.apis.chat.cleanup();
    }
  }
}

// ================================
// INITIALIZE APP
// ================================
const app = new FileManagerApp();

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => app.init());
} else {
  app.init();
}

// Export for global access
window.app = app;

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
  app.cleanup();
});