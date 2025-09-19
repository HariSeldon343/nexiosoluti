/**
 * Task API Module
 * Handles all task and project management API operations
 *
 * @module TaskAPI
 */

(function(window) {
    'use strict';

    // Ensure APIConfig is loaded
    if (!window.APIConfig) {
        console.error('TaskAPI: APIConfig module is required but not loaded');
        return;
    }

    const { get, post, put, delete: deleteRequest } = window.APIConfig;

    /**
     * Task API endpoints and operations
     */
    const TaskAPI = {
        /**
         * Get all tasks
         * @param {Object} filters - Filter options
         * @param {string} filters.status - Task status (pending/in_progress/completed/archived)
         * @param {string} filters.priority - Priority level (low/medium/high/urgent)
         * @param {string} filters.assignee - Assignee user ID
         * @param {string} filters.project - Project ID
         * @param {string} filters.due_date - Due date filter
         * @param {string} filters.sort - Sort field
         * @param {string} filters.order - Sort order (asc/desc)
         * @returns {Promise<Object>} Tasks data
         */
        getTasks: async function(filters = {}) {
            try {
                const response = await get('tasks.php', {
                    action: 'list',
                    ...filters
                });

                if (response.success) {
                    return {
                        success: true,
                        tasks: response.data || [],
                        total: response.total || 0
                    };
                }

                throw new Error(response.message || 'Failed to fetch tasks');
            } catch (error) {
                console.error('TaskAPI.getTasks error:', error);
                throw error;
            }
        },

        /**
         * Get a single task by ID
         * @param {string|number} taskId - Task ID
         * @returns {Promise<Object>} Task data
         */
        getTask: async function(taskId) {
            try {
                const response = await get('tasks.php', {
                    action: 'get',
                    id: taskId
                });

                if (response.success) {
                    return {
                        success: true,
                        task: response.data
                    };
                }

                throw new Error(response.message || 'Task not found');
            } catch (error) {
                console.error('TaskAPI.getTask error:', error);
                throw error;
            }
        },

        /**
         * Create a new task
         * @param {Object} taskData - Task data
         * @param {string} taskData.title - Task title
         * @param {string} taskData.description - Task description
         * @param {string} taskData.status - Initial status
         * @param {string} taskData.priority - Priority level
         * @param {string} taskData.due_date - Due date
         * @param {Array} taskData.assignees - Assignee user IDs
         * @param {string} taskData.project_id - Project ID
         * @param {Array} taskData.tags - Task tags
         * @param {Array} taskData.attachments - File attachments
         * @returns {Promise<Object>} Created task data
         */
        createTask: async function(taskData) {
            try {
                const response = await post('tasks.php', {
                    action: 'create',
                    ...taskData
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('task:created', response.data);

                    return {
                        success: true,
                        task: response.data,
                        message: response.message || 'Task created successfully'
                    };
                }

                throw new Error(response.message || 'Failed to create task');
            } catch (error) {
                console.error('TaskAPI.createTask error:', error);
                throw error;
            }
        },

        /**
         * Update an existing task
         * @param {string|number} taskId - Task ID
         * @param {Object} updates - Task updates
         * @returns {Promise<Object>} Updated task data
         */
        updateTask: async function(taskId, updates) {
            try {
                const response = await put('tasks.php', {
                    action: 'update',
                    id: taskId,
                    ...updates
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('task:updated', response.data);

                    return {
                        success: true,
                        task: response.data,
                        message: response.message || 'Task updated successfully'
                    };
                }

                throw new Error(response.message || 'Failed to update task');
            } catch (error) {
                console.error('TaskAPI.updateTask error:', error);
                throw error;
            }
        },

        /**
         * Delete a task
         * @param {string|number} taskId - Task ID
         * @returns {Promise<Object>} Deletion result
         */
        deleteTask: async function(taskId) {
            try {
                const response = await deleteRequest(`tasks.php?id=${taskId}`);

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('task:deleted', { id: taskId });

                    return {
                        success: true,
                        message: response.message || 'Task deleted successfully'
                    };
                }

                throw new Error(response.message || 'Failed to delete task');
            } catch (error) {
                console.error('TaskAPI.deleteTask error:', error);
                throw error;
            }
        },

        /**
         * Move task to different status/column (Kanban)
         * @param {string|number} taskId - Task ID
         * @param {string} newStatus - New status
         * @param {number} position - Position in new column
         * @param {string} targetColumn - Target column ID (for custom boards)
         * @returns {Promise<Object>} Updated task data
         */
        moveTask: async function(taskId, newStatus, position = null, targetColumn = null) {
            try {
                const response = await post('tasks.php', {
                    action: 'move',
                    id: taskId,
                    status: newStatus,
                    position: position,
                    column: targetColumn
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('task:moved', response.data);

                    return {
                        success: true,
                        task: response.data,
                        message: response.message || 'Task moved successfully'
                    };
                }

                throw new Error(response.message || 'Failed to move task');
            } catch (error) {
                console.error('TaskAPI.moveTask error:', error);
                throw error;
            }
        },

        /**
         * Update task status
         * @param {string|number} taskId - Task ID
         * @param {string} status - New status
         * @returns {Promise<Object>} Updated task data
         */
        updateStatus: async function(taskId, status) {
            try {
                const response = await post('tasks.php', {
                    action: 'update_status',
                    id: taskId,
                    status: status
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('task:status:changed', response.data);

                    return {
                        success: true,
                        task: response.data,
                        message: response.message || 'Status updated successfully'
                    };
                }

                throw new Error(response.message || 'Failed to update status');
            } catch (error) {
                console.error('TaskAPI.updateStatus error:', error);
                throw error;
            }
        },

        /**
         * Assign task to users
         * @param {string|number} taskId - Task ID
         * @param {Array<string>} userIds - User IDs to assign
         * @returns {Promise<Object>} Assignment result
         */
        assignTask: async function(taskId, userIds) {
            try {
                const response = await post('tasks.php', {
                    action: 'assign',
                    id: taskId,
                    assignees: userIds
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('task:assigned', response.data);

                    return {
                        success: true,
                        task: response.data,
                        message: response.message || 'Task assigned successfully'
                    };
                }

                throw new Error(response.message || 'Failed to assign task');
            } catch (error) {
                console.error('TaskAPI.assignTask error:', error);
                throw error;
            }
        },

        /**
         * Add comment to task
         * @param {string|number} taskId - Task ID
         * @param {string} comment - Comment text
         * @param {Array} attachments - File attachments
         * @returns {Promise<Object>} Comment data
         */
        addComment: async function(taskId, comment, attachments = []) {
            try {
                const response = await post('tasks.php', {
                    action: 'add_comment',
                    id: taskId,
                    comment: comment,
                    attachments: attachments
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('task:comment:added', response.data);

                    return {
                        success: true,
                        comment: response.data,
                        message: response.message || 'Comment added successfully'
                    };
                }

                throw new Error(response.message || 'Failed to add comment');
            } catch (error) {
                console.error('TaskAPI.addComment error:', error);
                throw error;
            }
        },

        /**
         * Get task comments
         * @param {string|number} taskId - Task ID
         * @returns {Promise<Object>} Comments data
         */
        getComments: async function(taskId) {
            try {
                const response = await get('tasks.php', {
                    action: 'get_comments',
                    id: taskId
                });

                return {
                    success: true,
                    comments: response.data || []
                };
            } catch (error) {
                console.error('TaskAPI.getComments error:', error);
                throw error;
            }
        },

        /**
         * Get task activity log
         * @param {string|number} taskId - Task ID
         * @returns {Promise<Object>} Activity log data
         */
        getActivity: async function(taskId) {
            try {
                const response = await get('tasks.php', {
                    action: 'get_activity',
                    id: taskId
                });

                return {
                    success: true,
                    activities: response.data || []
                };
            } catch (error) {
                console.error('TaskAPI.getActivity error:', error);
                throw error;
            }
        },

        /**
         * Create subtask
         * @param {string|number} parentId - Parent task ID
         * @param {Object} subtaskData - Subtask data
         * @returns {Promise<Object>} Created subtask data
         */
        createSubtask: async function(parentId, subtaskData) {
            try {
                const response = await post('tasks.php', {
                    action: 'create_subtask',
                    parent_id: parentId,
                    ...subtaskData
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('task:subtask:created', response.data);

                    return {
                        success: true,
                        subtask: response.data,
                        message: response.message || 'Subtask created successfully'
                    };
                }

                throw new Error(response.message || 'Failed to create subtask');
            } catch (error) {
                console.error('TaskAPI.createSubtask error:', error);
                throw error;
            }
        },

        /**
         * Get task statistics
         * @param {Object} filters - Statistics filters
         * @returns {Promise<Object>} Task statistics
         */
        getStatistics: async function(filters = {}) {
            try {
                const response = await get('tasks.php', {
                    action: 'statistics',
                    ...filters
                });

                return {
                    success: true,
                    stats: response.data || {}
                };
            } catch (error) {
                console.error('TaskAPI.getStatistics error:', error);
                throw error;
            }
        },

        /**
         * Bulk update tasks
         * @param {Array<string>} taskIds - Task IDs to update
         * @param {Object} updates - Updates to apply
         * @returns {Promise<Object>} Update result
         */
        bulkUpdate: async function(taskIds, updates) {
            try {
                const response = await post('tasks.php', {
                    action: 'bulk_update',
                    ids: taskIds,
                    updates: updates
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('tasks:bulk:updated', response.data);

                    return {
                        success: true,
                        updated: response.data.count || 0,
                        message: response.message || 'Tasks updated successfully'
                    };
                }

                throw new Error(response.message || 'Failed to update tasks');
            } catch (error) {
                console.error('TaskAPI.bulkUpdate error:', error);
                throw error;
            }
        },

        /**
         * Search tasks
         * @param {string} query - Search query
         * @param {Object} filters - Additional filters
         * @returns {Promise<Object>} Search results
         */
        searchTasks: async function(query, filters = {}) {
            try {
                const response = await get('tasks.php', {
                    action: 'search',
                    q: query,
                    ...filters
                });

                return {
                    success: true,
                    tasks: response.data || [],
                    total: response.total || 0
                };
            } catch (error) {
                console.error('TaskAPI.searchTasks error:', error);
                throw error;
            }
        },

        /**
         * Export tasks
         * @param {string} format - Export format (csv/json/pdf)
         * @param {Object} filters - Export filters
         * @returns {Promise<Blob>} Exported data
         */
        exportTasks: async function(format = 'csv', filters = {}) {
            try {
                const params = new URLSearchParams({
                    action: 'export',
                    format: format,
                    ...filters
                });

                const url = window.APIConfig.buildApiUrl(`tasks.php?${params}`);
                const response = await fetch(url, {
                    ...window.APIConfig.getDefaultFetchOptions()
                });

                if (response.ok) {
                    return await response.blob();
                }

                throw new Error('Failed to export tasks');
            } catch (error) {
                console.error('TaskAPI.exportTasks error:', error);
                throw error;
            }
        },

        /**
         * Get task templates
         * @returns {Promise<Object>} Task templates
         */
        getTemplates: async function() {
            try {
                const response = await get('tasks.php', {
                    action: 'templates'
                });

                return {
                    success: true,
                    templates: response.data || []
                };
            } catch (error) {
                console.error('TaskAPI.getTemplates error:', error);
                throw error;
            }
        },

        /**
         * Create task from template
         * @param {string|number} templateId - Template ID
         * @param {Object} overrides - Fields to override from template
         * @returns {Promise<Object>} Created task data
         */
        createFromTemplate: async function(templateId, overrides = {}) {
            try {
                const response = await post('tasks.php', {
                    action: 'create_from_template',
                    template_id: templateId,
                    ...overrides
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('task:created:from:template', response.data);

                    return {
                        success: true,
                        task: response.data,
                        message: response.message || 'Task created from template'
                    };
                }

                throw new Error(response.message || 'Failed to create task from template');
            } catch (error) {
                console.error('TaskAPI.createFromTemplate error:', error);
                throw error;
            }
        },

        /**
         * Subscribe to task updates via polling
         * @param {Function} callback - Callback for updates
         * @param {Object} filters - Filter which tasks to watch
         * @param {number} interval - Polling interval in ms (default 15s)
         * @returns {Function} Unsubscribe function
         */
        subscribeToUpdates: function(callback, filters = {}, interval = 15000) {
            let lastUpdate = new Date().toISOString();

            const pollForUpdates = async () => {
                try {
                    const response = await get('tasks.php', {
                        action: 'changes',
                        since: lastUpdate,
                        ...filters
                    });

                    if (response.success && response.data && response.data.length > 0) {
                        callback(response.data);
                        lastUpdate = new Date().toISOString();
                    }
                } catch (error) {
                    console.error('Task polling error:', error);
                }
            };

            const intervalId = setInterval(pollForUpdates, interval);

            // Return unsubscribe function
            return () => clearInterval(intervalId);
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
    window.TaskAPI = TaskAPI;

})(window);