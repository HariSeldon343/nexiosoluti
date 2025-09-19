/**
 * UI Components Module
 * Reusable UI components (modals, toasts, etc.)
 */

export class Components {
  constructor(appState) {
    this.state = appState;
    this.activeModals = new Set();
    this.toastContainer = null;
    this.initializeContainers();
  }

  // ================================
  // INITIALIZATION
  // ================================
  initializeContainers() {
    // Create toast container if not exists
    if (!document.querySelector('.toast-container')) {
      this.toastContainer = document.createElement('div');
      this.toastContainer.className = 'toast-container';
      document.body.appendChild(this.toastContainer);
    } else {
      this.toastContainer = document.querySelector('.toast-container');
    }

    // Create context menu if not exists
    if (!document.querySelector('.context-menu')) {
      const contextMenu = document.createElement('div');
      contextMenu.className = 'context-menu';
      document.body.appendChild(contextMenu);
    }

    // Create modal backdrop if not exists
    if (!document.querySelector('.modal-backdrop')) {
      const backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop';
      backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) {
          this.closeTopModal();
        }
      });
      document.body.appendChild(backdrop);
    }
  }

  // ================================
  // TOAST NOTIFICATIONS
  // ================================
  showToast(type = 'info', title, message, duration = 5000) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    const icons = {
      success: '<svg class="toast-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
      error: '<svg class="toast-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
      warning: '<svg class="toast-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
      info: '<svg class="toast-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>'
    };

    toast.innerHTML = `
      ${icons[type] || icons.info}
      <div class="toast-content">
        <div class="toast-title">${this.escapeHtml(title)}</div>
        ${message ? `<div class="toast-message">${this.escapeHtml(message)}</div>` : ''}
      </div>
      <button class="toast-close">
        <svg viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
        </svg>
      </button>
    `;

    // Add close handler
    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => this.removeToast(toast));

    // Add to container
    this.toastContainer.appendChild(toast);

    // Auto remove after duration
    if (duration > 0) {
      setTimeout(() => this.removeToast(toast), duration);
    }

    return toast;
  }

  removeToast(toast) {
    if (!toast || !toast.parentNode) return;

    toast.classList.add('removing');
    setTimeout(() => {
      toast.remove();
    }, 300);
  }

  // ================================
  // MODALS
  // ================================
  createModal(options = {}) {
    const {
      title = '',
      content = '',
      size = 'medium',
      closeable = true,
      className = '',
      buttons = []
    } = options;

    const modalId = `modal-${Date.now()}`;
    const modal = document.createElement('div');
    modal.className = `modal ${className} modal-${size}`;
    modal.id = modalId;

    modal.innerHTML = `
      <div class="modal-header">
        <h2 class="modal-title">${this.escapeHtml(title)}</h2>
        ${closeable ? `
          <button class="modal-close" data-modal-close>
            <svg viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
          </button>
        ` : ''}
      </div>
      <div class="modal-body">
        ${content}
      </div>
      ${buttons.length > 0 ? `
        <div class="modal-footer">
          ${buttons.map((btn, index) => `
            <button class="btn btn-${btn.variant || 'secondary'}" data-button-index="${index}">
              ${btn.icon ? btn.icon : ''}
              ${this.escapeHtml(btn.text)}
            </button>
          `).join('')}
        </div>
      ` : ''}
    `;

    // Add event handlers
    modal.querySelectorAll('[data-modal-close]').forEach(el => {
      el.addEventListener('click', () => this.closeModal(modalId));
    });

    buttons.forEach((btn, index) => {
      const buttonEl = modal.querySelector(`[data-button-index="${index}"]`);
      if (buttonEl && btn.onClick) {
        buttonEl.addEventListener('click', () => {
          const result = btn.onClick();
          if (result !== false) {
            this.closeModal(modalId);
          }
        });
      }
    });

    return { modalId, modal };
  }

  showModal(options) {
    const { modalId, modal } = this.createModal(options);

    const backdrop = document.querySelector('.modal-backdrop');
    backdrop.appendChild(modal);
    backdrop.classList.add('active');

    this.activeModals.add(modalId);

    return modalId;
  }

  closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.remove();
    this.activeModals.delete(modalId);

    if (this.activeModals.size === 0) {
      document.querySelector('.modal-backdrop').classList.remove('active');
    }
  }

  closeTopModal() {
    const modals = Array.from(this.activeModals);
    if (modals.length > 0) {
      this.closeModal(modals[modals.length - 1]);
    }
  }

  closeAllModals() {
    this.activeModals.forEach(modalId => {
      document.getElementById(modalId)?.remove();
    });
    this.activeModals.clear();
    document.querySelector('.modal-backdrop').classList.remove('active');
  }

  // ================================
  // SPECIFIC MODALS
  // ================================
  showUploadModal() {
    const modalId = this.showModal({
      title: 'Carica File',
      content: `
        <div class="upload-modal-content">
          <input type="file" id="upload-input" multiple hidden>
          <div class="upload-dropzone" id="upload-dropzone">
            <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            <h3>Trascina i file qui</h3>
            <p>oppure</p>
            <button class="btn btn-primary" onclick="document.getElementById('upload-input').click()">
              Seleziona file
            </button>
            <p class="text-sm text-gray-500 mt-md">Dimensione massima: 100MB per file</p>
          </div>
          <div id="upload-preview" class="upload-preview hidden"></div>
        </div>
      `,
      size: 'large',
      buttons: [
        {
          text: 'Annulla',
          variant: 'secondary',
          onClick: () => true
        },
        {
          text: 'Carica',
          variant: 'primary',
          onClick: () => {
            this.handleUploadSubmit();
            return false; // Keep modal open during upload
          }
        }
      ]
    });

    // Set up file input and dropzone
    this.setupUploadHandlers(modalId);

    return modalId;
  }

  setupUploadHandlers(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    const fileInput = modal.querySelector('#upload-input');
    const dropzone = modal.querySelector('#upload-dropzone');
    const preview = modal.querySelector('#upload-preview');

    let selectedFiles = [];

    // File input change
    fileInput.addEventListener('change', (e) => {
      selectedFiles = Array.from(e.target.files);
      this.showUploadPreview(selectedFiles, preview, dropzone);
    });

    // Drag and drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
      });
    });

    ['dragenter', 'dragover'].forEach(eventName => {
      dropzone.addEventListener(eventName, () => {
        dropzone.classList.add('dragover');
      });
    });

    ['dragleave', 'drop'].forEach(eventName => {
      dropzone.addEventListener(eventName, () => {
        dropzone.classList.remove('dragover');
      });
    });

    dropzone.addEventListener('drop', (e) => {
      selectedFiles = Array.from(e.dataTransfer.files);
      this.showUploadPreview(selectedFiles, preview, dropzone);
    });

    // Store files for upload
    modal.selectedFiles = selectedFiles;
  }

  showUploadPreview(files, previewContainer, dropzone) {
    if (files.length === 0) return;

    dropzone.classList.add('hidden');
    previewContainer.classList.remove('hidden');

    const fileList = files.map((file, index) => `
      <div class="upload-preview-item" data-file-index="${index}">
        <div class="upload-preview-icon">
          ${this.getFileIconByName(file.name)}
        </div>
        <div class="upload-preview-info">
          <div class="upload-preview-name">${this.escapeHtml(file.name)}</div>
          <div class="upload-preview-size">${this.formatFileSize(file.size)}</div>
        </div>
        <button class="upload-preview-remove" data-remove-index="${index}">
          <svg viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
          </svg>
        </button>
      </div>
    `).join('');

    previewContainer.innerHTML = `
      <div class="upload-preview-header">
        <span>${files.length} file selezionati</span>
        <button class="btn btn-ghost btn-sm" id="upload-add-more">
          Aggiungi altri file
        </button>
      </div>
      <div class="upload-preview-list">
        ${fileList}
      </div>
    `;

    // Add remove handlers
    previewContainer.querySelectorAll('[data-remove-index]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const index = parseInt(e.currentTarget.dataset.removeIndex);
        files.splice(index, 1);
        if (files.length === 0) {
          dropzone.classList.remove('hidden');
          previewContainer.classList.add('hidden');
        } else {
          this.showUploadPreview(files, previewContainer, dropzone);
        }
      });
    });

    // Add more files handler
    previewContainer.querySelector('#upload-add-more')?.addEventListener('click', () => {
      document.getElementById('upload-input').click();
    });
  }

  async handleUploadSubmit() {
    const modal = document.querySelector('.modal');
    const files = modal?.selectedFiles;

    if (!files || files.length === 0) {
      this.showToast('warning', 'Attenzione', 'Seleziona almeno un file da caricare');
      return;
    }

    // Show upload progress
    const progressModal = this.showUploadProgress(files);

    try {
      // Trigger upload through app
      await window.app.handleFileUpload(files);
      this.closeAllModals();
    } catch (error) {
      this.showToast('error', 'Errore', 'Caricamento fallito');
    }
  }

  showUploadProgress(files) {
    const progressId = `progress-${Date.now()}`;
    const container = document.createElement('div');
    container.className = 'upload-progress';
    container.id = progressId;

    container.innerHTML = `
      <div class="upload-progress-header">
        <span class="upload-progress-title">Caricamento in corso...</span>
        <button class="upload-progress-close">
          <svg viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
          </svg>
        </button>
      </div>
      <div class="upload-progress-body">
        ${files.map((file, index) => `
          <div class="upload-item" data-file-index="${index}">
            <div class="upload-item-header">
              <span class="upload-item-name">
                ${this.getFileIconByName(file.name)}
                ${this.escapeHtml(file.name)}
              </span>
              <span class="upload-item-size">${this.formatFileSize(file.size)}</span>
            </div>
            <div class="upload-item-progress">
              <div class="upload-item-progress-bar" style="width: 0%"></div>
            </div>
          </div>
        `).join('')}
      </div>
    `;

    document.body.appendChild(container);

    // Close button handler
    container.querySelector('.upload-progress-close').addEventListener('click', () => {
      container.remove();
    });

    // Return update function
    return (fileIndex, percent, status = 'uploading') => {
      const item = container.querySelector(`[data-file-index="${fileIndex}"]`);
      if (!item) return;

      const progressBar = item.querySelector('.upload-item-progress-bar');
      progressBar.style.width = `${percent}%`;

      if (status === 'success') {
        item.classList.add('success');
      } else if (status === 'error') {
        item.classList.add('error');
      }
    };
  }

  showNewFolderModal() {
    return this.showModal({
      title: 'Nuova Cartella',
      content: `
        <div class="form-group">
          <label for="folder-name" class="form-label">Nome cartella</label>
          <input type="text" id="folder-name" class="form-input" placeholder="Inserisci nome cartella" autofocus>
        </div>
      `,
      buttons: [
        {
          text: 'Annulla',
          variant: 'secondary',
          onClick: () => true
        },
        {
          text: 'Crea',
          variant: 'primary',
          onClick: async () => {
            const input = document.getElementById('folder-name');
            const name = input?.value.trim();

            if (!name) {
              this.showToast('warning', 'Attenzione', 'Inserisci un nome per la cartella');
              return false;
            }

            try {
              await window.app.fileManager.createFolder(name, window.app.state.currentFolder);
              await window.app.loadFolderTree();
              await window.app.loadFiles();
              this.showToast('success', 'Cartella creata', `La cartella "${name}" è stata creata`);
              return true;
            } catch (error) {
              this.showToast('error', 'Errore', 'Impossibile creare la cartella');
              return false;
            }
          }
        }
      ]
    });
  }

  showRenameModal(item, isFolder = false) {
    return this.showModal({
      title: isFolder ? 'Rinomina Cartella' : 'Rinomina File',
      content: `
        <div class="form-group">
          <label for="new-name" class="form-label">Nuovo nome</label>
          <input type="text" id="new-name" class="form-input" value="${this.escapeHtml(item.name)}" autofocus>
        </div>
      `,
      buttons: [
        {
          text: 'Annulla',
          variant: 'secondary',
          onClick: () => true
        },
        {
          text: 'Rinomina',
          variant: 'primary',
          onClick: async () => {
            const input = document.getElementById('new-name');
            const newName = input?.value.trim();

            if (!newName || newName === item.name) {
              return true;
            }

            try {
              if (isFolder) {
                await window.app.fileManager.renameFolder(item.id, newName);
                await window.app.loadFolderTree();
              } else {
                await window.app.fileManager.renameFile(item.id, newName);
              }
              await window.app.loadFiles();
              this.showToast('success', 'Rinominato', `${isFolder ? 'Cartella' : 'File'} rinominato con successo`);
              return true;
            } catch (error) {
              this.showToast('error', 'Errore', 'Impossibile rinominare');
              return false;
            }
          }
        }
      ]
    });
  }

  showConfirmDialog(title, message, confirmText = 'Conferma', cancelText = 'Annulla') {
    return new Promise((resolve) => {
      this.showModal({
        title,
        content: `<p>${this.escapeHtml(message)}</p>`,
        buttons: [
          {
            text: cancelText,
            variant: 'secondary',
            onClick: () => {
              resolve(false);
              return true;
            }
          },
          {
            text: confirmText,
            variant: 'danger',
            onClick: () => {
              resolve(true);
              return true;
            }
          }
        ]
      });
    });
  }

  showPromptDialog(title, message, defaultValue = '') {
    return new Promise((resolve) => {
      this.showModal({
        title,
        content: `
          <p>${this.escapeHtml(message)}</p>
          <input type="text" id="prompt-input" class="form-input mt-md" value="${this.escapeHtml(defaultValue)}" autofocus>
        `,
        buttons: [
          {
            text: 'Annulla',
            variant: 'secondary',
            onClick: () => {
              resolve(null);
              return true;
            }
          },
          {
            text: 'OK',
            variant: 'primary',
            onClick: () => {
              const value = document.getElementById('prompt-input')?.value;
              resolve(value);
              return true;
            }
          }
        ]
      });
    });
  }

  showImagePreview(file) {
    const imageUrl = window.app.fileManager.getFilePreviewUrl(file.id);

    this.showModal({
      title: file.name,
      className: 'preview-modal',
      content: `
        <div class="preview-content">
          <img src="${imageUrl}" alt="${this.escapeHtml(file.name)}" class="preview-image">
        </div>
        <div class="preview-info">
          <div class="preview-info-label">Dimensione:</div>
          <div class="preview-info-value">${this.formatFileSize(file.size)}</div>
          <div class="preview-info-label">Tipo:</div>
          <div class="preview-info-value">${file.extension.toUpperCase()}</div>
          <div class="preview-info-label">Modificato:</div>
          <div class="preview-info-value">${new Date(file.updated_at).toLocaleString('it-IT')}</div>
        </div>
      `,
      size: 'large',
      buttons: [
        {
          text: 'Scarica',
          variant: 'primary',
          onClick: () => {
            window.app.downloadFile(file.id);
            return false; // Keep modal open
          }
        },
        {
          text: 'Chiudi',
          variant: 'secondary',
          onClick: () => true
        }
      ]
    });
  }

  showPdfPreview(file) {
    const pdfUrl = window.app.fileManager.getFilePreviewUrl(file.id);

    this.showModal({
      title: file.name,
      className: 'preview-modal',
      content: `
        <div class="preview-content">
          <iframe src="${pdfUrl}" class="preview-pdf"></iframe>
        </div>
      `,
      size: 'large',
      buttons: [
        {
          text: 'Scarica',
          variant: 'primary',
          onClick: () => {
            window.app.downloadFile(file.id);
            return false;
          }
        },
        {
          text: 'Chiudi',
          variant: 'secondary',
          onClick: () => true
        }
      ]
    });
  }

  showShareModal(shareUrl) {
    this.showModal({
      title: 'Condividi File',
      content: `
        <div class="share-modal-content">
          <p class="mb-md">Usa questo link per condividere il file:</p>
          <div class="share-link-container">
            <input type="text" id="share-link" class="form-input" value="${shareUrl}" readonly>
            <button class="btn btn-primary" onclick="components.copyShareLink()">
              Copia
            </button>
          </div>
          <div class="share-options mt-lg">
            <h4 class="mb-sm">Opzioni di condivisione:</h4>
            <label class="checkbox-label">
              <input type="checkbox" id="share-password">
              <span>Proteggi con password</span>
            </label>
            <label class="checkbox-label">
              <input type="checkbox" id="share-expiry">
              <span>Imposta scadenza</span>
            </label>
          </div>
        </div>
      `,
      buttons: [
        {
          text: 'Fatto',
          variant: 'primary',
          onClick: () => true
        }
      ]
    });
  }

  copyShareLink() {
    const input = document.getElementById('share-link');
    if (input) {
      input.select();
      document.execCommand('copy');
      this.showToast('success', 'Copiato', 'Link copiato negli appunti');
    }
  }

  showFileInfoModal(file) {
    this.showModal({
      title: 'Informazioni File',
      content: `
        <div class="file-info-modal">
          <div class="file-info-icon">
            ${this.getFileIconByExtension(file.extension)}
          </div>
          <div class="file-info-details">
            <div class="info-row">
              <span class="info-label">Nome:</span>
              <span class="info-value">${this.escapeHtml(file.name)}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Tipo:</span>
              <span class="info-value">${file.extension.toUpperCase()}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Dimensione:</span>
              <span class="info-value">${this.formatFileSize(file.size)}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Creato:</span>
              <span class="info-value">${new Date(file.created_at).toLocaleString('it-IT')}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Modificato:</span>
              <span class="info-value">${new Date(file.updated_at).toLocaleString('it-IT')}</span>
            </div>
            ${file.shared ? `
              <div class="info-row">
                <span class="info-label">Condiviso:</span>
                <span class="info-value">Sì</span>
              </div>
            ` : ''}
          </div>
        </div>
      `,
      buttons: [
        {
          text: 'Chiudi',
          variant: 'secondary',
          onClick: () => true
        }
      ]
    });
  }

  showFolderSelectModal() {
    return new Promise(async (resolve) => {
      const folders = await window.app.fileManager.getFolderTree();

      this.showModal({
        title: 'Seleziona Cartella',
        content: `
          <div class="folder-select-modal">
            <div class="folder-select-tree">
              ${this.buildFolderSelectTree(folders)}
            </div>
          </div>
        `,
        buttons: [
          {
            text: 'Annulla',
            variant: 'secondary',
            onClick: () => {
              resolve(null);
              return true;
            }
          },
          {
            text: 'Seleziona',
            variant: 'primary',
            onClick: () => {
              const selected = document.querySelector('.folder-select-item.selected');
              resolve(selected?.dataset.folderId || null);
              return true;
            }
          }
        ]
      });

      // Add selection handlers
      document.querySelectorAll('.folder-select-item').forEach(item => {
        item.addEventListener('click', () => {
          document.querySelectorAll('.folder-select-item').forEach(i => {
            i.classList.remove('selected');
          });
          item.classList.add('selected');
        });
      });
    });
  }

  buildFolderSelectTree(folders, level = 0) {
    let html = '';

    folders.forEach(folder => {
      html += `
        <div class="folder-select-item" data-folder-id="${folder.id}" style="padding-left: ${level * 20}px;">
          <svg class="folder-icon" viewBox="0 0 20 20" fill="currentColor">
            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
          </svg>
          <span>${this.escapeHtml(folder.name)}</span>
        </div>
      `;

      if (folder.children && folder.children.length > 0) {
        html += this.buildFolderSelectTree(folder.children, level + 1);
      }
    });

    return html;
  }

  // ================================
  // DROPDOWN MENUS
  // ================================
  toggleDropdown(trigger) {
    const dropdownId = trigger.dataset.dropdown;
    let dropdown = document.getElementById(dropdownId);

    if (!dropdown) {
      dropdown = this.createDropdown(trigger);
    }

    if (dropdown.classList.contains('active')) {
      this.hideDropdown(dropdown);
    } else {
      this.showDropdown(dropdown, trigger);
    }
  }

  createDropdown(trigger) {
    const dropdownId = `dropdown-${Date.now()}`;
    trigger.dataset.dropdown = dropdownId;

    const dropdown = document.createElement('div');
    dropdown.id = dropdownId;
    dropdown.className = 'dropdown-menu';

    // Get dropdown content based on trigger
    if (trigger.classList.contains('user-avatar')) {
      dropdown.innerHTML = `
        <div class="dropdown-item">
          <svg class="dropdown-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
          </svg>
          Profilo
        </div>
        <div class="dropdown-item">
          <svg class="dropdown-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
          </svg>
          Impostazioni
        </div>
        <div class="dropdown-divider"></div>
        <div class="dropdown-item" onclick="window.app.logout()">
          <svg class="dropdown-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/>
          </svg>
          Esci
        </div>
      `;
    }

    document.body.appendChild(dropdown);
    return dropdown;
  }

  showDropdown(dropdown, trigger) {
    // Hide all other dropdowns
    document.querySelectorAll('.dropdown-menu.active').forEach(d => {
      this.hideDropdown(d);
    });

    // Position dropdown
    const rect = trigger.getBoundingClientRect();
    dropdown.style.top = `${rect.bottom + 5}px`;
    dropdown.style.left = `${rect.left}px`;

    // Adjust if goes off screen
    setTimeout(() => {
      const dropRect = dropdown.getBoundingClientRect();
      if (dropRect.right > window.innerWidth) {
        dropdown.style.left = `${rect.right - dropRect.width}px`;
      }
    }, 0);

    dropdown.classList.add('active');

    // Close on click outside
    const closeHandler = (e) => {
      if (!dropdown.contains(e.target) && !trigger.contains(e.target)) {
        this.hideDropdown(dropdown);
        document.removeEventListener('click', closeHandler);
      }
    };

    setTimeout(() => {
      document.addEventListener('click', closeHandler);
    }, 0);
  }

  hideDropdown(dropdown) {
    dropdown.classList.remove('active');
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

  getFileIconByName(filename) {
    const extension = filename.split('.').pop().toLowerCase();
    return this.getFileIconByExtension(extension);
  }

  getFileIconByExtension(extension) {
    const iconMap = {
      pdf: '📄',
      doc: '📝',
      docx: '📝',
      xls: '📊',
      xlsx: '📊',
      ppt: '📎',
      pptx: '📎',
      txt: '📃',
      jpg: '🖼️',
      jpeg: '🖼️',
      png: '🖼️',
      gif: '🖼️',
      mp3: '🎵',
      mp4: '🎬',
      zip: '📦',
      rar: '📦'
    };

    return `<span class="file-type-icon">${iconMap[extension] || '📎'}</span>`;
  }
}

// Export for use in main app
window.Components = Components;