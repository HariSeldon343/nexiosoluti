/**
 * FileManager Module
 * Handles all file operations and API communications
 */

export class FileManager {
  constructor(appState) {
    this.state = appState;
    // Use centralized APIConfig if available, otherwise fallback
    this.apiBase = window.APIConfig ? window.APIConfig.getApiBaseUrl() : '/collabora/api';
    this.uploadController = null;
  }

  // ================================
  // API HELPERS
  // ================================
  async request(endpoint, options = {}) {
    // If APIConfig is available, use it for better path resolution
    if (window.APIConfig && options.method !== 'GET') {
      try {
        // Clean endpoint (remove leading slash if present)
        const cleanEndpoint = endpoint.startsWith('/') ? endpoint.substring(1) : endpoint;

        if (options.method === 'POST' || !options.method) {
          return await APIConfig.post(cleanEndpoint, options.body, options);
        } else if (options.method === 'PUT') {
          return await APIConfig.put(cleanEndpoint, options.body, options);
        } else if (options.method === 'DELETE') {
          return await APIConfig.delete(cleanEndpoint, options);
        }
      } catch (error) {
        console.error('API request error:', error);
        throw error;
      }
    }

    // Fallback to direct fetch
    const defaultOptions = {
      headers: {
        'X-CSRF-Token': this.state.csrfToken
      }
    };

    // Merge options
    const finalOptions = {
      ...defaultOptions,
      ...options,
      headers: {
        ...defaultOptions.headers,
        ...(options.headers || {})
      }
    };

    try {
      const response = await fetch(`${this.apiBase}${endpoint}`, finalOptions);

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        return await response.json();
      }

      return response;
    } catch (error) {
      console.error('API request error:', error);
      throw error;
    }
  }

  // ================================
  // USER & TENANT
  // ================================
  async getUserData() {
    return await this.request('/auth.php?action=getUserData');
  }

  async getTenantInfo() {
    return await this.request('/auth.php?action=getTenantInfo');
  }

  // ================================
  // FOLDER OPERATIONS
  // ================================
  async getFolderTree() {
    const response = await this.request('/folders.php?action=tree');
    return response.tree || [];
  }

  async createFolder(name, parentId = null) {
    return await this.request('/folders.php?action=create', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        name,
        parent_id: parentId
      })
    });
  }

  async renameFolder(folderId, newName) {
    return await this.request('/folders.php?action=rename', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: folderId,
        name: newName
      })
    });
  }

  async moveFolder(folderId, targetParentId) {
    return await this.request('/folders.php?action=move', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: folderId,
        parent_id: targetParentId
      })
    });
  }

  async deleteFolder(folderId) {
    return await this.request('/folders.php?action=delete', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: folderId
      })
    });
  }

  async getFolderPath(folderId) {
    return await this.request(`/folders.php?action=path&id=${folderId}`);
  }

  // ================================
  // FILE OPERATIONS
  // ================================
  async getFiles(folderId = null) {
    let url = '/files.php?action=list';
    if (folderId) {
      url += `&folder_id=${folderId}`;
    }
    const response = await this.request(url);
    return response.files || [];
  }

  async searchFiles(query) {
    const response = await this.request(`/files.php?action=search&q=${encodeURIComponent(query)}`);
    return response.files || [];
  }

  async getFileInfo(fileId) {
    return await this.request(`/files.php?action=info&id=${fileId}`);
  }

  async getRecentFiles(limit = 10) {
    const response = await this.request(`/files.php?action=recent&limit=${limit}`);
    return response.files || [];
  }

  async getSharedFiles() {
    const response = await this.request('/files.php?action=shared');
    return response.files || [];
  }

  async getTrashFiles() {
    const response = await this.request('/files.php?action=trash');
    return response.files || [];
  }

  // ================================
  // FILE UPLOAD
  // ================================
  async uploadFiles(files, folderId = null, progressCallback = null) {
    // Cancel any existing upload
    if (this.uploadController) {
      this.uploadController.abort();
    }

    // Create new abort controller
    this.uploadController = new AbortController();

    const formData = new FormData();
    formData.append('csrf_token', this.state.csrfToken);

    if (folderId) {
      formData.append('folder_id', folderId);
    }

    // Add files to form data
    for (let i = 0; i < files.length; i++) {
      formData.append('files[]', files[i]);
    }

    try {
      // Create XMLHttpRequest for progress tracking
      return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();

        // Track upload progress
        xhr.upload.addEventListener('progress', (e) => {
          if (e.lengthComputable && progressCallback) {
            const percentComplete = (e.loaded / e.total) * 100;
            progressCallback({
              loaded: e.loaded,
              total: e.total,
              percent: percentComplete
            });
          }
        });

        // Handle completion
        xhr.addEventListener('load', () => {
          if (xhr.status === 200) {
            try {
              const response = JSON.parse(xhr.responseText);
              resolve(response);
            } catch (error) {
              reject(new Error('Invalid response format'));
            }
          } else {
            reject(new Error(`Upload failed with status ${xhr.status}`));
          }
        });

        // Handle errors
        xhr.addEventListener('error', () => {
          reject(new Error('Upload failed'));
        });

        // Handle abort
        xhr.addEventListener('abort', () => {
          reject(new Error('Upload cancelled'));
        });

        // Set up abort controller
        this.uploadController.signal.addEventListener('abort', () => {
          xhr.abort();
        });

        // Send request - use APIConfig if available for URL building
        const uploadUrl = window.APIConfig
          ? APIConfig.buildApiUrl('files.php?action=upload')
          : `${this.apiBase}/files.php?action=upload`;

        xhr.open('POST', uploadUrl);
        xhr.setRequestHeader('X-CSRF-Token', this.state.csrfToken);
        xhr.send(formData);
      });
    } catch (error) {
      console.error('Upload error:', error);
      throw error;
    } finally {
      this.uploadController = null;
    }
  }

  cancelUpload() {
    if (this.uploadController) {
      this.uploadController.abort();
      this.uploadController = null;
    }
  }

  // ================================
  // FILE MANIPULATION
  // ================================
  async renameFile(fileId, newName) {
    return await this.request('/files.php?action=rename', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: fileId,
        name: newName
      })
    });
  }

  async moveFile(fileId, targetFolderId) {
    return await this.request('/files.php?action=move', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: fileId,
        folder_id: targetFolderId
      })
    });
  }

  async moveFiles(fileIds, targetFolderId) {
    return await this.request('/files.php?action=moveMultiple', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        ids: fileIds,
        folder_id: targetFolderId
      })
    });
  }

  async copyFile(fileId) {
    return await this.request('/files.php?action=copy', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: fileId
      })
    });
  }

  async deleteFile(fileId) {
    return await this.request('/files.php?action=delete', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: fileId
      })
    });
  }

  async deleteFiles(fileIds) {
    return await this.request('/files.php?action=deleteMultiple', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        ids: fileIds
      })
    });
  }

  async restoreFile(fileId) {
    return await this.request('/files.php?action=restore', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: fileId
      })
    });
  }

  async permanentlyDeleteFile(fileId) {
    return await this.request('/files.php?action=permanentDelete', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: fileId
      })
    });
  }

  async emptyTrash() {
    return await this.request('/files.php?action=emptyTrash', {
      method: 'POST'
    });
  }

  // ================================
  // FILE DOWNLOAD & PREVIEW
  // ================================
  downloadFile(fileId) {
    const link = document.createElement('a');
    const downloadUrl = window.APIConfig
      ? APIConfig.buildApiUrl(`files.php?action=download&id=${fileId}`)
      : `${this.apiBase}/files.php?action=download&id=${fileId}`;
    link.href = downloadUrl;
    link.download = '';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  downloadFiles(fileIds) {
    // Download multiple files as zip
    const params = new URLSearchParams();
    params.append('action', 'downloadMultiple');
    fileIds.forEach(id => params.append('ids[]', id));

    const link = document.createElement('a');
    const downloadUrl = window.APIConfig
      ? APIConfig.buildApiUrl(`files.php?${params.toString()}`)
      : `${this.apiBase}/files.php?${params.toString()}`;
    link.href = downloadUrl;
    link.download = 'files.zip';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  getFilePreviewUrl(fileId) {
    return window.APIConfig
      ? APIConfig.buildApiUrl(`files.php?action=preview&id=${fileId}`)
      : `${this.apiBase}/files.php?action=preview&id=${fileId}`;
  }

  getFileThumbnailUrl(fileId, size = 'medium') {
    return window.APIConfig
      ? APIConfig.buildApiUrl(`files.php?action=thumbnail&id=${fileId}&size=${size}`)
      : `${this.apiBase}/files.php?action=thumbnail&id=${fileId}&size=${size}`;
  }

  // ================================
  // FILE SHARING
  // ================================
  async shareFile(fileId, options = {}) {
    return await this.request('/files.php?action=share', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: fileId,
        ...options
      })
    });
  }

  async getShareLink(fileId) {
    const response = await this.request(`/files.php?action=getShareLink&id=${fileId}`);
    return response.link;
  }

  async revokeShare(shareId) {
    return await this.request('/files.php?action=revokeShare', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        share_id: shareId
      })
    });
  }

  async getFilePermissions(fileId) {
    return await this.request(`/files.php?action=permissions&id=${fileId}`);
  }

  async updateFilePermissions(fileId, permissions) {
    return await this.request('/files.php?action=updatePermissions', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: fileId,
        permissions
      })
    });
  }

  // ================================
  // FILE VERSIONING
  // ================================
  async getFileVersions(fileId) {
    const response = await this.request(`/files.php?action=versions&id=${fileId}`);
    return response.versions || [];
  }

  async restoreVersion(fileId, versionId) {
    return await this.request('/files.php?action=restoreVersion', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: fileId,
        version_id: versionId
      })
    });
  }

  async deleteVersion(fileId, versionId) {
    return await this.request('/files.php?action=deleteVersion', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: fileId,
        version_id: versionId
      })
    });
  }

  // ================================
  // FILE TAGS & METADATA
  // ================================
  async getFileTags(fileId) {
    const response = await this.request(`/files.php?action=tags&id=${fileId}`);
    return response.tags || [];
  }

  async addFileTags(fileId, tags) {
    return await this.request('/files.php?action=addTags', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: fileId,
        tags
      })
    });
  }

  async removeFileTag(fileId, tag) {
    return await this.request('/files.php?action=removeTag', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: fileId,
        tag
      })
    });
  }

  async updateFileMetadata(fileId, metadata) {
    return await this.request('/files.php?action=updateMetadata', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: fileId,
        metadata
      })
    });
  }

  // ================================
  // STORAGE & STATISTICS
  // ================================
  async getStorageStats() {
    return await this.request('/files.php?action=storageStats');
  }

  async getFileTypeStats() {
    return await this.request('/files.php?action=fileTypeStats');
  }

  async getActivityLog(limit = 50) {
    return await this.request(`/files.php?action=activityLog&limit=${limit}`);
  }

  // ================================
  // BATCH OPERATIONS
  // ================================
  async batchOperation(operation, fileIds, params = {}) {
    return await this.request('/files.php?action=batch', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        operation,
        ids: fileIds,
        ...params
      })
    });
  }

  // ================================
  // UTILITY METHODS
  // ================================
  validateFileName(name) {
    // Check for invalid characters
    const invalidChars = /[<>:"/\\|?*\x00-\x1F]/;
    if (invalidChars.test(name)) {
      return {
        valid: false,
        error: 'Il nome del file contiene caratteri non validi'
      };
    }

    // Check length
    if (name.length > 255) {
      return {
        valid: false,
        error: 'Il nome del file è troppo lungo (max 255 caratteri)'
      };
    }

    // Check for reserved names
    const reservedNames = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'LPT1'];
    const upperName = name.toUpperCase();
    if (reservedNames.includes(upperName)) {
      return {
        valid: false,
        error: 'Il nome del file è riservato dal sistema'
      };
    }

    return { valid: true };
  }

  getFileExtension(filename) {
    const parts = filename.split('.');
    return parts.length > 1 ? parts.pop().toLowerCase() : '';
  }

  getMimeType(extension) {
    const mimeTypes = {
      // Documents
      pdf: 'application/pdf',
      doc: 'application/msword',
      docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      xls: 'application/vnd.ms-excel',
      xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      ppt: 'application/vnd.ms-powerpoint',
      pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
      txt: 'text/plain',
      rtf: 'application/rtf',
      odt: 'application/vnd.oasis.opendocument.text',
      ods: 'application/vnd.oasis.opendocument.spreadsheet',

      // Images
      jpg: 'image/jpeg',
      jpeg: 'image/jpeg',
      png: 'image/png',
      gif: 'image/gif',
      bmp: 'image/bmp',
      svg: 'image/svg+xml',
      webp: 'image/webp',
      ico: 'image/x-icon',

      // Videos
      mp4: 'video/mp4',
      avi: 'video/x-msvideo',
      mov: 'video/quicktime',
      wmv: 'video/x-ms-wmv',
      flv: 'video/x-flv',
      webm: 'video/webm',
      mkv: 'video/x-matroska',

      // Audio
      mp3: 'audio/mpeg',
      wav: 'audio/wav',
      ogg: 'audio/ogg',
      m4a: 'audio/mp4',
      wma: 'audio/x-ms-wma',
      flac: 'audio/flac',

      // Archives
      zip: 'application/zip',
      rar: 'application/x-rar-compressed',
      tar: 'application/x-tar',
      gz: 'application/gzip',
      '7z': 'application/x-7z-compressed',

      // Code
      html: 'text/html',
      css: 'text/css',
      js: 'application/javascript',
      json: 'application/json',
      xml: 'application/xml',
      php: 'application/x-httpd-php',
      py: 'text/x-python',
      java: 'text/x-java-source',
      c: 'text/x-c',
      cpp: 'text/x-c++',
      cs: 'text/x-csharp',
      sql: 'application/sql'
    };

    return mimeTypes[extension] || 'application/octet-stream';
  }

  canPreview(extension) {
    const previewableTypes = [
      'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
      'pdf',
      'txt', 'html', 'css', 'js', 'json', 'xml',
      'mp4', 'webm',
      'mp3', 'wav', 'ogg'
    ];

    return previewableTypes.includes(extension.toLowerCase());
  }

  formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  sortFiles(files, sortBy = 'name', order = 'asc') {
    const sorted = [...files].sort((a, b) => {
      let aVal = a[sortBy];
      let bVal = b[sortBy];

      // Handle different data types
      if (sortBy === 'size') {
        aVal = parseInt(aVal) || 0;
        bVal = parseInt(bVal) || 0;
      } else if (sortBy === 'updated_at' || sortBy === 'created_at') {
        aVal = new Date(aVal).getTime();
        bVal = new Date(bVal).getTime();
      } else {
        // String comparison
        aVal = String(aVal).toLowerCase();
        bVal = String(bVal).toLowerCase();
      }

      if (aVal < bVal) return order === 'asc' ? -1 : 1;
      if (aVal > bVal) return order === 'asc' ? 1 : -1;
      return 0;
    });

    return sorted;
  }

  filterFiles(files, filters = {}) {
    let filtered = [...files];

    // Filter by type
    if (filters.type) {
      const types = Array.isArray(filters.type) ? filters.type : [filters.type];
      filtered = filtered.filter(file => {
        const ext = this.getFileExtension(file.name);
        return types.some(type => {
          switch (type) {
            case 'document':
              return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf'].includes(ext);
            case 'image':
              return ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'].includes(ext);
            case 'video':
              return ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'].includes(ext);
            case 'audio':
              return ['mp3', 'wav', 'ogg', 'm4a', 'wma', 'flac'].includes(ext);
            case 'archive':
              return ['zip', 'rar', 'tar', 'gz', '7z'].includes(ext);
            default:
              return ext === type;
          }
        });
      });
    }

    // Filter by size
    if (filters.minSize !== undefined) {
      filtered = filtered.filter(file => file.size >= filters.minSize);
    }
    if (filters.maxSize !== undefined) {
      filtered = filtered.filter(file => file.size <= filters.maxSize);
    }

    // Filter by date
    if (filters.dateFrom) {
      const fromDate = new Date(filters.dateFrom).getTime();
      filtered = filtered.filter(file => new Date(file.updated_at).getTime() >= fromDate);
    }
    if (filters.dateTo) {
      const toDate = new Date(filters.dateTo).getTime();
      filtered = filtered.filter(file => new Date(file.updated_at).getTime() <= toDate);
    }

    // Filter by tags
    if (filters.tags && filters.tags.length > 0) {
      filtered = filtered.filter(file => {
        if (!file.tags) return false;
        return filters.tags.every(tag => file.tags.includes(tag));
      });
    }

    return filtered;
  }
}