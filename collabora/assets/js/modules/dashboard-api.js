/**
 * Dashboard API Module
 * Handles dashboard widgets, notifications, reports, and metrics
 *
 * @module DashboardAPI
 */

(function(window) {
    'use strict';

    // Ensure APIConfig is loaded
    if (!window.APIConfig) {
        console.error('DashboardAPI: APIConfig module is required but not loaded');
        return;
    }

    const { get, post, put, delete: deleteRequest } = window.APIConfig;

    /**
     * Dashboard API endpoints and operations
     */
    const DashboardAPI = {
        // Active polling intervals
        _notificationInterval: null,
        _metricsInterval: null,

        /**
         * Get dashboard configuration
         * @param {string} dashboardId - Dashboard ID (optional, defaults to user's default)
         * @returns {Promise<Object>} Dashboard configuration
         */
        getDashboard: async function(dashboardId = null) {
            try {
                const params = {
                    action: 'get'
                };

                if (dashboardId) {
                    params.id = dashboardId;
                }

                const response = await get('dashboards.php', params);

                if (response.success) {
                    return {
                        success: true,
                        dashboard: response.data,
                        widgets: response.widgets || []
                    };
                }

                throw new Error(response.message || 'Failed to fetch dashboard');
            } catch (error) {
                console.error('DashboardAPI.getDashboard error:', error);
                throw error;
            }
        },

        /**
         * Get all available dashboards for user
         * @returns {Promise<Object>} Available dashboards
         */
        getDashboards: async function() {
            try {
                const response = await get('dashboards.php', {
                    action: 'list'
                });

                if (response.success) {
                    return {
                        success: true,
                        dashboards: response.data || [],
                        default: response.default_id || null
                    };
                }

                throw new Error(response.message || 'Failed to fetch dashboards');
            } catch (error) {
                console.error('DashboardAPI.getDashboards error:', error);
                throw error;
            }
        },

        /**
         * Create a new dashboard
         * @param {Object} dashboardData - Dashboard configuration
         * @param {string} dashboardData.name - Dashboard name
         * @param {string} dashboardData.description - Dashboard description
         * @param {Array} dashboardData.widgets - Initial widgets configuration
         * @param {boolean} dashboardData.is_default - Set as default dashboard
         * @returns {Promise<Object>} Created dashboard data
         */
        createDashboard: async function(dashboardData) {
            try {
                const response = await post('dashboards.php', {
                    action: 'create',
                    ...dashboardData
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('dashboard:created', response.data);

                    return {
                        success: true,
                        dashboard: response.data,
                        message: response.message || 'Dashboard created successfully'
                    };
                }

                throw new Error(response.message || 'Failed to create dashboard');
            } catch (error) {
                console.error('DashboardAPI.createDashboard error:', error);
                throw error;
            }
        },

        /**
         * Update dashboard configuration
         * @param {string} dashboardId - Dashboard ID
         * @param {Object} updates - Dashboard updates
         * @returns {Promise<Object>} Updated dashboard data
         */
        updateDashboard: async function(dashboardId, updates) {
            try {
                const response = await put('dashboards.php', {
                    action: 'update',
                    id: dashboardId,
                    ...updates
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('dashboard:updated', response.data);

                    return {
                        success: true,
                        dashboard: response.data,
                        message: response.message || 'Dashboard updated successfully'
                    };
                }

                throw new Error(response.message || 'Failed to update dashboard');
            } catch (error) {
                console.error('DashboardAPI.updateDashboard error:', error);
                throw error;
            }
        },

        /**
         * Delete dashboard
         * @param {string} dashboardId - Dashboard ID
         * @returns {Promise<Object>} Deletion result
         */
        deleteDashboard: async function(dashboardId) {
            try {
                const response = await deleteRequest(`dashboards.php?id=${dashboardId}`);

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('dashboard:deleted', { id: dashboardId });

                    return {
                        success: true,
                        message: response.message || 'Dashboard deleted successfully'
                    };
                }

                throw new Error(response.message || 'Failed to delete dashboard');
            } catch (error) {
                console.error('DashboardAPI.deleteDashboard error:', error);
                throw error;
            }
        },

        /**
         * Get widgets for dashboard
         * @param {string} dashboardId - Dashboard ID
         * @returns {Promise<Object>} Widgets data
         */
        getWidgets: async function(dashboardId) {
            try {
                const response = await get('widgets.php', {
                    action: 'list',
                    dashboard_id: dashboardId
                });

                if (response.success) {
                    return {
                        success: true,
                        widgets: response.data || []
                    };
                }

                throw new Error(response.message || 'Failed to fetch widgets');
            } catch (error) {
                console.error('DashboardAPI.getWidgets error:', error);
                throw error;
            }
        },

        /**
         * Add widget to dashboard
         * @param {string} dashboardId - Dashboard ID
         * @param {Object} widgetData - Widget configuration
         * @param {string} widgetData.type - Widget type
         * @param {Object} widgetData.config - Widget-specific configuration
         * @param {Object} widgetData.position - Position on dashboard
         * @param {Object} widgetData.size - Widget size
         * @returns {Promise<Object>} Added widget data
         */
        addWidget: async function(dashboardId, widgetData) {
            try {
                const response = await post('widgets.php', {
                    action: 'add',
                    dashboard_id: dashboardId,
                    ...widgetData
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('widget:added', response.data);

                    return {
                        success: true,
                        widget: response.data,
                        message: response.message || 'Widget added successfully'
                    };
                }

                throw new Error(response.message || 'Failed to add widget');
            } catch (error) {
                console.error('DashboardAPI.addWidget error:', error);
                throw error;
            }
        },

        /**
         * Update widget configuration
         * @param {string} widgetId - Widget ID
         * @param {Object} updates - Widget updates
         * @returns {Promise<Object>} Updated widget data
         */
        updateWidget: async function(widgetId, updates) {
            try {
                const response = await put('widgets.php', {
                    action: 'update',
                    widget_id: widgetId,
                    ...updates
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('widget:updated', response.data);

                    return {
                        success: true,
                        widget: response.data,
                        message: response.message || 'Widget updated successfully'
                    };
                }

                throw new Error(response.message || 'Failed to update widget');
            } catch (error) {
                console.error('DashboardAPI.updateWidget error:', error);
                throw error;
            }
        },

        /**
         * Remove widget from dashboard
         * @param {string} widgetId - Widget ID
         * @returns {Promise<Object>} Removal result
         */
        removeWidget: async function(widgetId) {
            try {
                const response = await deleteRequest(`widgets.php?widget_id=${widgetId}`);

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('widget:removed', { widget_id: widgetId });

                    return {
                        success: true,
                        message: response.message || 'Widget removed successfully'
                    };
                }

                throw new Error(response.message || 'Failed to remove widget');
            } catch (error) {
                console.error('DashboardAPI.removeWidget error:', error);
                throw error;
            }
        },

        /**
         * Refresh widget data
         * @param {string} widgetId - Widget ID
         * @returns {Promise<Object>} Fresh widget data
         */
        refreshWidget: async function(widgetId) {
            try {
                const response = await get('widgets.php', {
                    action: 'refresh',
                    widget_id: widgetId
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('widget:refreshed', response.data);

                    return {
                        success: true,
                        data: response.data
                    };
                }

                throw new Error(response.message || 'Failed to refresh widget');
            } catch (error) {
                console.error('DashboardAPI.refreshWidget error:', error);
                throw error;
            }
        },

        /**
         * Get notifications
         * @param {Object} options - Query options
         * @param {boolean} options.unread - Only unread notifications
         * @param {string} options.type - Notification type filter
         * @param {number} options.limit - Number of notifications
         * @returns {Promise<Object>} Notifications data
         */
        getNotifications: async function(options = {}) {
            try {
                const response = await get('notifications.php', {
                    action: 'list',
                    ...options
                });

                if (response.success) {
                    return {
                        success: true,
                        notifications: response.data || [],
                        unread_count: response.unread_count || 0,
                        total: response.total || 0
                    };
                }

                throw new Error(response.message || 'Failed to fetch notifications');
            } catch (error) {
                console.error('DashboardAPI.getNotifications error:', error);
                throw error;
            }
        },

        /**
         * Mark notifications as read
         * @param {Array<string>} notificationIds - Notification IDs to mark as read
         * @returns {Promise<Object>} Mark read result
         */
        markNotificationsRead: async function(notificationIds) {
            try {
                const response = await post('notifications.php', {
                    action: 'mark_read',
                    ids: notificationIds
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('notifications:read', { ids: notificationIds });

                    return {
                        success: true,
                        message: response.message || 'Notifications marked as read'
                    };
                }

                throw new Error(response.message || 'Failed to mark notifications');
            } catch (error) {
                console.error('DashboardAPI.markNotificationsRead error:', error);
                throw error;
            }
        },

        /**
         * Delete notifications
         * @param {Array<string>} notificationIds - Notification IDs to delete
         * @returns {Promise<Object>} Deletion result
         */
        deleteNotifications: async function(notificationIds) {
            try {
                const response = await post('notifications.php', {
                    action: 'delete',
                    ids: notificationIds
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('notifications:deleted', { ids: notificationIds });

                    return {
                        success: true,
                        message: response.message || 'Notifications deleted'
                    };
                }

                throw new Error(response.message || 'Failed to delete notifications');
            } catch (error) {
                console.error('DashboardAPI.deleteNotifications error:', error);
                throw error;
            }
        },

        /**
         * Update notification preferences
         * @param {Object} preferences - Notification preferences
         * @returns {Promise<Object>} Update result
         */
        updateNotificationPreferences: async function(preferences) {
            try {
                const response = await post('notifications.php', {
                    action: 'update_preferences',
                    ...preferences
                });

                if (response.success) {
                    return {
                        success: true,
                        preferences: response.data,
                        message: response.message || 'Preferences updated'
                    };
                }

                throw new Error(response.message || 'Failed to update preferences');
            } catch (error) {
                console.error('DashboardAPI.updateNotificationPreferences error:', error);
                throw error;
            }
        },

        /**
         * Generate report
         * @param {Object} reportData - Report parameters
         * @param {string} reportData.type - Report type
         * @param {string} reportData.period - Time period
         * @param {Object} reportData.filters - Report filters
         * @param {string} reportData.format - Output format (json/pdf/csv)
         * @returns {Promise<Object|Blob>} Report data or file
         */
        generateReport: async function(reportData) {
            try {
                if (reportData.format && reportData.format !== 'json') {
                    // Binary response for PDF/CSV
                    const params = new URLSearchParams({
                        action: 'generate',
                        ...reportData
                    });

                    const url = window.APIConfig.buildApiUrl(`reports.php?${params}`);
                    const response = await fetch(url, {
                        ...window.APIConfig.getDefaultFetchOptions()
                    });

                    if (response.ok) {
                        return await response.blob();
                    }

                    throw new Error('Failed to generate report');
                } else {
                    // JSON response
                    const response = await post('reports.php', {
                        action: 'generate',
                        ...reportData
                    });

                    if (response.success) {
                        return {
                            success: true,
                            report: response.data,
                            metadata: response.metadata || {}
                        };
                    }

                    throw new Error(response.message || 'Failed to generate report');
                }
            } catch (error) {
                console.error('DashboardAPI.generateReport error:', error);
                throw error;
            }
        },

        /**
         * Get saved reports
         * @returns {Promise<Object>} Saved reports list
         */
        getSavedReports: async function() {
            try {
                const response = await get('reports.php', {
                    action: 'list'
                });

                if (response.success) {
                    return {
                        success: true,
                        reports: response.data || []
                    };
                }

                throw new Error(response.message || 'Failed to fetch saved reports');
            } catch (error) {
                console.error('DashboardAPI.getSavedReports error:', error);
                throw error;
            }
        },

        /**
         * Schedule report
         * @param {Object} scheduleData - Schedule configuration
         * @returns {Promise<Object>} Schedule result
         */
        scheduleReport: async function(scheduleData) {
            try {
                const response = await post('reports.php', {
                    action: 'schedule',
                    ...scheduleData
                });

                if (response.success) {
                    return {
                        success: true,
                        schedule: response.data,
                        message: response.message || 'Report scheduled successfully'
                    };
                }

                throw new Error(response.message || 'Failed to schedule report');
            } catch (error) {
                console.error('DashboardAPI.scheduleReport error:', error);
                throw error;
            }
        },

        /**
         * Get system metrics
         * @param {Object} options - Metrics options
         * @param {Array<string>} options.metrics - Specific metrics to fetch
         * @param {string} options.period - Time period
         * @returns {Promise<Object>} Metrics data
         */
        getMetrics: async function(options = {}) {
            try {
                const response = await get('metrics.php', {
                    action: 'get',
                    ...options
                });

                if (response.success) {
                    return {
                        success: true,
                        metrics: response.data || {},
                        timestamp: response.timestamp || new Date().toISOString()
                    };
                }

                throw new Error(response.message || 'Failed to fetch metrics');
            } catch (error) {
                console.error('DashboardAPI.getMetrics error:', error);
                throw error;
            }
        },

        /**
         * Get activity feed
         * @param {Object} options - Feed options
         * @param {number} options.limit - Number of items
         * @param {string} options.since - Get activities since timestamp
         * @returns {Promise<Object>} Activity feed data
         */
        getActivityFeed: async function(options = {}) {
            try {
                const response = await get('dashboards.php', {
                    action: 'activity_feed',
                    ...options
                });

                if (response.success) {
                    return {
                        success: true,
                        activities: response.data || [],
                        has_more: response.has_more || false
                    };
                }

                throw new Error(response.message || 'Failed to fetch activity feed');
            } catch (error) {
                console.error('DashboardAPI.getActivityFeed error:', error);
                throw error;
            }
        },

        /**
         * Export dashboard configuration
         * @param {string} dashboardId - Dashboard ID
         * @returns {Promise<Object>} Dashboard export data
         */
        exportDashboard: async function(dashboardId) {
            try {
                const response = await get('dashboards.php', {
                    action: 'export',
                    id: dashboardId
                });

                if (response.success) {
                    return {
                        success: true,
                        export: response.data
                    };
                }

                throw new Error(response.message || 'Failed to export dashboard');
            } catch (error) {
                console.error('DashboardAPI.exportDashboard error:', error);
                throw error;
            }
        },

        /**
         * Import dashboard configuration
         * @param {Object} importData - Dashboard configuration to import
         * @returns {Promise<Object>} Imported dashboard data
         */
        importDashboard: async function(importData) {
            try {
                const response = await post('dashboards.php', {
                    action: 'import',
                    config: importData
                });

                if (response.success) {
                    return {
                        success: true,
                        dashboard: response.data,
                        message: response.message || 'Dashboard imported successfully'
                    };
                }

                throw new Error(response.message || 'Failed to import dashboard');
            } catch (error) {
                console.error('DashboardAPI.importDashboard error:', error);
                throw error;
            }
        },

        /**
         * Start notification polling
         * @param {Function} callback - Callback for new notifications
         * @param {number} interval - Polling interval in ms (default 60s)
         * @returns {Function} Stop polling function
         */
        startNotificationPolling: function(callback, interval = 60000) {
            // Stop existing polling if any
            this.stopNotificationPolling();

            let lastCheck = new Date().toISOString();

            const poll = async () => {
                try {
                    const response = await get('notifications.php', {
                        action: 'check_new',
                        since: lastCheck
                    });

                    if (response.success && response.data && response.data.length > 0) {
                        callback(response.data);
                        lastCheck = new Date().toISOString();
                    }
                } catch (error) {
                    console.error('Notification polling error:', error);
                }
            };

            // Start polling
            this._notificationInterval = setInterval(poll, interval);

            // Initial check
            poll();

            // Return stop function
            return () => this.stopNotificationPolling();
        },

        /**
         * Stop notification polling
         */
        stopNotificationPolling: function() {
            if (this._notificationInterval) {
                clearInterval(this._notificationInterval);
                this._notificationInterval = null;
            }
        },

        /**
         * Start metrics auto-refresh
         * @param {Function} callback - Callback for metric updates
         * @param {Array<string>} metrics - Metrics to monitor
         * @param {number} interval - Refresh interval in ms (default 30s)
         * @returns {Function} Stop refresh function
         */
        startMetricsRefresh: function(callback, metrics = [], interval = 30000) {
            // Stop existing refresh if any
            this.stopMetricsRefresh();

            const refresh = async () => {
                try {
                    const response = await this.getMetrics({ metrics });
                    if (response.success) {
                        callback(response.metrics);
                    }
                } catch (error) {
                    console.error('Metrics refresh error:', error);
                }
            };

            // Start refresh
            this._metricsInterval = setInterval(refresh, interval);

            // Initial refresh
            refresh();

            // Return stop function
            return () => this.stopMetricsRefresh();
        },

        /**
         * Stop metrics refresh
         */
        stopMetricsRefresh: function() {
            if (this._metricsInterval) {
                clearInterval(this._metricsInterval);
                this._metricsInterval = null;
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
        },

        /**
         * Cleanup all active intervals
         */
        cleanup: function() {
            this.stopNotificationPolling();
            this.stopMetricsRefresh();
        }
    };

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        DashboardAPI.cleanup();
    });

    // Export to global scope
    window.DashboardAPI = DashboardAPI;

})(window);