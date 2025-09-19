/**
 * Sharing API Module
 * Handles file sharing, versioning, comments, and approvals
 *
 * @module SharingAPI
 */

(function(window) {
    'use strict';

    // Ensure APIConfig is loaded
    if (!window.APIConfig) {
        console.error('SharingAPI: APIConfig module is required but not loaded');
        return;
    }

    const { get, post, put, delete: deleteRequest } = window.APIConfig;

    /**
     * Sharing API endpoints and operations
     */
    const SharingAPI = {
        /**
         * Create a share link for file/folder
         * @param {Object} shareData - Share data
         * @param {string|number} shareData.item_id - File or folder ID
         * @param {string} shareData.item_type - Type (file/folder)
         * @param {string} shareData.permission - Permission level (view/download/edit)
         * @param {string} shareData.expires_at - Expiration date (optional)
         * @param {string} shareData.password - Password protection (optional)
         * @param {number} shareData.max_downloads - Download limit (optional)
         * @returns {Promise<Object>} Share link data
         */
        createShareLink: async function(shareData) {
            try {
                const response = await post('share-links.php', {
                    action: 'create',
                    ...shareData
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('sharing:link:created', response.data);

                    return {
                        success: true,
                        share: response.data,
                        link: response.data.public_url,
                        token: response.data.token,
                        message: response.message || 'Share link created successfully'
                    };
                }

                throw new Error(response.message || 'Failed to create share link');
            } catch (error) {
                console.error('SharingAPI.createShareLink error:', error);
                throw error;
            }
        },

        /**
         * Get all share links for an item
         * @param {string|number} itemId - File or folder ID
         * @param {string} itemType - Type (file/folder)
         * @returns {Promise<Object>} Share links data
         */
        getShareLinks: async function(itemId, itemType = 'file') {
            try {
                const response = await get('share-links.php', {
                    action: 'list',
                    item_id: itemId,
                    item_type: itemType
                });

                if (response.success) {
                    return {
                        success: true,
                        shares: response.data || []
                    };
                }

                throw new Error(response.message || 'Failed to fetch share links');
            } catch (error) {
                console.error('SharingAPI.getShareLinks error:', error);
                throw error;
            }
        },

        /**
         * Update share link settings
         * @param {string} token - Share token
         * @param {Object} updates - Updates to apply
         * @returns {Promise<Object>} Updated share data
         */
        updateShareLink: async function(token, updates) {
            try {
                const response = await put('share-links.php', {
                    action: 'update',
                    token: token,
                    ...updates
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('sharing:link:updated', response.data);

                    return {
                        success: true,
                        share: response.data,
                        message: response.message || 'Share link updated successfully'
                    };
                }

                throw new Error(response.message || 'Failed to update share link');
            } catch (error) {
                console.error('SharingAPI.updateShareLink error:', error);
                throw error;
            }
        },

        /**
         * Revoke/delete share link
         * @param {string} token - Share token
         * @returns {Promise<Object>} Deletion result
         */
        revokeShareLink: async function(token) {
            try {
                const response = await deleteRequest(`share-links.php?token=${token}`);

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('sharing:link:revoked', { token: token });

                    return {
                        success: true,
                        message: response.message || 'Share link revoked successfully'
                    };
                }

                throw new Error(response.message || 'Failed to revoke share link');
            } catch (error) {
                console.error('SharingAPI.revokeShareLink error:', error);
                throw error;
            }
        },

        /**
         * Access public share (with optional password)
         * @param {string} token - Share token
         * @param {string} password - Password if required
         * @returns {Promise<Object>} Shared item data
         */
        accessPublicShare: async function(token, password = null) {
            try {
                const params = {
                    token: token
                };

                if (password) {
                    params.password = password;
                }

                const response = await get('public.php', params);

                if (response.success) {
                    return {
                        success: true,
                        item: response.data,
                        permissions: response.permissions || []
                    };
                }

                throw new Error(response.message || 'Failed to access share');
            } catch (error) {
                console.error('SharingAPI.accessPublicShare error:', error);
                throw error;
            }
        },

        /**
         * Download from public share
         * @param {string} token - Share token
         * @param {string} password - Password if required
         * @returns {Promise<Blob>} File blob
         */
        downloadPublicShare: async function(token, password = null) {
            try {
                const params = new URLSearchParams({
                    token: token,
                    action: 'download'
                });

                if (password) {
                    params.append('password', password);
                }

                const url = window.APIConfig.buildApiUrl(`public.php?${params}`);
                const response = await fetch(url, {
                    ...window.APIConfig.getDefaultFetchOptions()
                });

                if (response.ok) {
                    return await response.blob();
                }

                throw new Error('Failed to download shared file');
            } catch (error) {
                console.error('SharingAPI.downloadPublicShare error:', error);
                throw error;
            }
        },

        /**
         * Share with specific users
         * @param {string|number} itemId - File or folder ID
         * @param {string} itemType - Type (file/folder)
         * @param {Array<Object>} users - User share data
         * @returns {Promise<Object>} Share result
         */
        shareWithUsers: async function(itemId, itemType, users) {
            try {
                const response = await post('share-links.php', {
                    action: 'share_with_users',
                    item_id: itemId,
                    item_type: itemType,
                    users: users
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('sharing:users:added', response.data);

                    return {
                        success: true,
                        shares: response.data,
                        message: response.message || 'Shared with users successfully'
                    };
                }

                throw new Error(response.message || 'Failed to share with users');
            } catch (error) {
                console.error('SharingAPI.shareWithUsers error:', error);
                throw error;
            }
        },

        /**
         * Get file versions
         * @param {string|number} fileId - File ID
         * @returns {Promise<Object>} Versions data
         */
        getVersions: async function(fileId) {
            try {
                const response = await get('versions.php', {
                    action: 'list',
                    file_id: fileId
                });

                if (response.success) {
                    return {
                        success: true,
                        versions: response.data || [],
                        current: response.current || null
                    };
                }

                throw new Error(response.message || 'Failed to fetch versions');
            } catch (error) {
                console.error('SharingAPI.getVersions error:', error);
                throw error;
            }
        },

        /**
         * Upload new version of file
         * @param {string|number} fileId - File ID
         * @param {File} file - New version file
         * @param {string} comment - Version comment
         * @param {Function} onProgress - Upload progress callback
         * @returns {Promise<Object>} New version data
         */
        uploadVersion: async function(fileId, file, comment = '', onProgress = null) {
            try {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('file_id', fileId);
                formData.append('comment', comment);
                formData.append('action', 'upload');

                const response = await window.APIConfig.uploadWithProgress(
                    'versions.php',
                    formData,
                    onProgress
                );

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('file:version:uploaded', response.data);

                    return {
                        success: true,
                        version: response.data,
                        message: response.message || 'New version uploaded successfully'
                    };
                }

                throw new Error(response.message || 'Failed to upload version');
            } catch (error) {
                console.error('SharingAPI.uploadVersion error:', error);
                throw error;
            }
        },

        /**
         * Restore file version
         * @param {string|number} fileId - File ID
         * @param {string|number} versionId - Version ID to restore
         * @returns {Promise<Object>} Restore result
         */
        restoreVersion: async function(fileId, versionId) {
            try {
                const response = await post('versions.php', {
                    action: 'restore',
                    file_id: fileId,
                    version_id: versionId
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('file:version:restored', response.data);

                    return {
                        success: true,
                        file: response.data,
                        message: response.message || 'Version restored successfully'
                    };
                }

                throw new Error(response.message || 'Failed to restore version');
            } catch (error) {
                console.error('SharingAPI.restoreVersion error:', error);
                throw error;
            }
        },

        /**
         * Delete file version
         * @param {string|number} fileId - File ID
         * @param {string|number} versionId - Version ID
         * @returns {Promise<Object>} Deletion result
         */
        deleteVersion: async function(fileId, versionId) {
            try {
                const response = await deleteRequest(`versions.php?file_id=${fileId}&version_id=${versionId}`);

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('file:version:deleted', {
                        file_id: fileId,
                        version_id: versionId
                    });

                    return {
                        success: true,
                        message: response.message || 'Version deleted successfully'
                    };
                }

                throw new Error(response.message || 'Failed to delete version');
            } catch (error) {
                console.error('SharingAPI.deleteVersion error:', error);
                throw error;
            }
        },

        /**
         * Get comments for file/folder
         * @param {string|number} itemId - File or folder ID
         * @param {string} itemType - Type (file/folder)
         * @returns {Promise<Object>} Comments data
         */
        getComments: async function(itemId, itemType = 'file') {
            try {
                const response = await get('comments.php', {
                    action: 'list',
                    item_id: itemId,
                    item_type: itemType
                });

                if (response.success) {
                    return {
                        success: true,
                        comments: response.data || [],
                        total: response.total || 0
                    };
                }

                throw new Error(response.message || 'Failed to fetch comments');
            } catch (error) {
                console.error('SharingAPI.getComments error:', error);
                throw error;
            }
        },

        /**
         * Add comment to file/folder
         * @param {string|number} itemId - File or folder ID
         * @param {string} itemType - Type (file/folder)
         * @param {string} comment - Comment text
         * @param {Object} options - Additional options
         * @returns {Promise<Object>} Comment data
         */
        addComment: async function(itemId, itemType, comment, options = {}) {
            try {
                const response = await post('comments.php', {
                    action: 'add',
                    item_id: itemId,
                    item_type: itemType,
                    comment: comment,
                    ...options
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('comment:added', response.data);

                    return {
                        success: true,
                        comment: response.data,
                        message: response.message || 'Comment added successfully'
                    };
                }

                throw new Error(response.message || 'Failed to add comment');
            } catch (error) {
                console.error('SharingAPI.addComment error:', error);
                throw error;
            }
        },

        /**
         * Edit comment
         * @param {string|number} commentId - Comment ID
         * @param {string} newComment - New comment text
         * @returns {Promise<Object>} Updated comment data
         */
        editComment: async function(commentId, newComment) {
            try {
                const response = await put('comments.php', {
                    action: 'edit',
                    comment_id: commentId,
                    comment: newComment
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('comment:edited', response.data);

                    return {
                        success: true,
                        comment: response.data,
                        message: response.message || 'Comment updated successfully'
                    };
                }

                throw new Error(response.message || 'Failed to edit comment');
            } catch (error) {
                console.error('SharingAPI.editComment error:', error);
                throw error;
            }
        },

        /**
         * Delete comment
         * @param {string|number} commentId - Comment ID
         * @returns {Promise<Object>} Deletion result
         */
        deleteComment: async function(commentId) {
            try {
                const response = await deleteRequest(`comments.php?comment_id=${commentId}`);

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('comment:deleted', { comment_id: commentId });

                    return {
                        success: true,
                        message: response.message || 'Comment deleted successfully'
                    };
                }

                throw new Error(response.message || 'Failed to delete comment');
            } catch (error) {
                console.error('SharingAPI.deleteComment error:', error);
                throw error;
            }
        },

        /**
         * Request approval for file
         * @param {string|number} fileId - File ID
         * @param {Object} approvalData - Approval request data
         * @returns {Promise<Object>} Approval request data
         */
        requestApproval: async function(fileId, approvalData) {
            try {
                const response = await post('approvals.php', {
                    action: 'request',
                    file_id: fileId,
                    ...approvalData
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('approval:requested', response.data);

                    return {
                        success: true,
                        approval: response.data,
                        message: response.message || 'Approval requested successfully'
                    };
                }

                throw new Error(response.message || 'Failed to request approval');
            } catch (error) {
                console.error('SharingAPI.requestApproval error:', error);
                throw error;
            }
        },

        /**
         * Get approval requests
         * @param {Object} filters - Filter options
         * @returns {Promise<Object>} Approval requests data
         */
        getApprovals: async function(filters = {}) {
            try {
                const response = await get('approvals.php', {
                    action: 'list',
                    ...filters
                });

                if (response.success) {
                    return {
                        success: true,
                        approvals: response.data || [],
                        pending: response.pending || 0
                    };
                }

                throw new Error(response.message || 'Failed to fetch approvals');
            } catch (error) {
                console.error('SharingAPI.getApprovals error:', error);
                throw error;
            }
        },

        /**
         * Respond to approval request
         * @param {string|number} approvalId - Approval ID
         * @param {string} decision - Decision (approve/reject)
         * @param {string} comment - Optional comment
         * @returns {Promise<Object>} Approval response result
         */
        respondToApproval: async function(approvalId, decision, comment = '') {
            try {
                const response = await post('approvals.php', {
                    action: 'respond',
                    approval_id: approvalId,
                    decision: decision,
                    comment: comment
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('approval:responded', response.data);

                    return {
                        success: true,
                        approval: response.data,
                        message: response.message || `File ${decision}d successfully`
                    };
                }

                throw new Error(response.message || 'Failed to respond to approval');
            } catch (error) {
                console.error('SharingAPI.respondToApproval error:', error);
                throw error;
            }
        },

        /**
         * Get sharing activity log
         * @param {string|number} itemId - File or folder ID
         * @param {string} itemType - Type (file/folder)
         * @returns {Promise<Object>} Activity log data
         */
        getSharingActivity: async function(itemId, itemType = 'file') {
            try {
                const response = await get('share-links.php', {
                    action: 'activity',
                    item_id: itemId,
                    item_type: itemType
                });

                return {
                    success: true,
                    activities: response.data || []
                };
            } catch (error) {
                console.error('SharingAPI.getSharingActivity error:', error);
                throw error;
            }
        },

        /**
         * Get share statistics
         * @param {string} token - Share token
         * @returns {Promise<Object>} Share statistics
         */
        getShareStatistics: async function(token) {
            try {
                const response = await get('share-links.php', {
                    action: 'statistics',
                    token: token
                });

                return {
                    success: true,
                    stats: response.data || {}
                };
            } catch (error) {
                console.error('SharingAPI.getShareStatistics error:', error);
                throw error;
            }
        },

        /**
         * Internal: Trigger custom events for UI updates
         * @private
         */
        _triggerEvent: function(eventName, data) {
            const event = new CustomEvent(eventName, {
                detail: data,
                bubbles: true,
                cancelable: true
            });
            window.dispatchEvent(event);
        }
    };

    // Export to global scope
    window.SharingAPI = SharingAPI;

})(window);