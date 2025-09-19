/**
 * Calendar API Module
 * Handles all calendar and event-related API operations
 *
 * @module CalendarAPI
 */

(function(window) {
    'use strict';

    // Ensure APIConfig is loaded
    if (!window.APIConfig) {
        console.error('CalendarAPI: APIConfig module is required but not loaded');
        return;
    }

    const { get, post, put, delete: deleteRequest } = window.APIConfig;

    /**
     * Calendar API endpoints and operations
     */
    const CalendarAPI = {
        /**
         * Get all events for current user
         * @param {Object} options - Query options
         * @param {string} options.start - Start date (ISO format)
         * @param {string} options.end - End date (ISO format)
         * @param {string} options.view - Calendar view (month/week/day)
         * @returns {Promise<Object>} Events data
         */
        getEvents: async function(options = {}) {
            try {
                const response = await get('events.php', {
                    action: 'list',
                    ...options
                });

                if (response.success) {
                    return {
                        success: true,
                        events: response.data || []
                    };
                }

                throw new Error(response.message || 'Failed to fetch events');
            } catch (error) {
                console.error('CalendarAPI.getEvents error:', error);
                throw error;
            }
        },

        /**
         * Get a single event by ID
         * @param {string|number} eventId - Event ID
         * @returns {Promise<Object>} Event data
         */
        getEvent: async function(eventId) {
            try {
                const response = await get('events.php', {
                    action: 'get',
                    id: eventId
                });

                if (response.success) {
                    return {
                        success: true,
                        event: response.data
                    };
                }

                throw new Error(response.message || 'Event not found');
            } catch (error) {
                console.error('CalendarAPI.getEvent error:', error);
                throw error;
            }
        },

        /**
         * Create a new event
         * @param {Object} eventData - Event data
         * @param {string} eventData.title - Event title
         * @param {string} eventData.start - Start date/time
         * @param {string} eventData.end - End date/time
         * @param {string} eventData.description - Event description
         * @param {string} eventData.location - Event location
         * @param {string} eventData.color - Event color
         * @param {boolean} eventData.allDay - All day event flag
         * @param {Array} eventData.participants - List of participant emails
         * @param {Object} eventData.recurrence - Recurrence rules
         * @returns {Promise<Object>} Created event data
         */
        createEvent: async function(eventData) {
            try {
                const response = await post('events.php', {
                    action: 'create',
                    ...eventData
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('calendar:event:created', response.data);

                    return {
                        success: true,
                        event: response.data,
                        message: response.message || 'Event created successfully'
                    };
                }

                throw new Error(response.message || 'Failed to create event');
            } catch (error) {
                console.error('CalendarAPI.createEvent error:', error);
                throw error;
            }
        },

        /**
         * Update an existing event
         * @param {string|number} eventId - Event ID
         * @param {Object} updates - Event updates
         * @returns {Promise<Object>} Updated event data
         */
        updateEvent: async function(eventId, updates) {
            try {
                const response = await put('events.php', {
                    action: 'update',
                    id: eventId,
                    ...updates
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('calendar:event:updated', response.data);

                    return {
                        success: true,
                        event: response.data,
                        message: response.message || 'Event updated successfully'
                    };
                }

                throw new Error(response.message || 'Failed to update event');
            } catch (error) {
                console.error('CalendarAPI.updateEvent error:', error);
                throw error;
            }
        },

        /**
         * Delete an event
         * @param {string|number} eventId - Event ID
         * @returns {Promise<Object>} Deletion result
         */
        deleteEvent: async function(eventId) {
            try {
                const response = await deleteRequest(`events.php?id=${eventId}`);

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('calendar:event:deleted', { id: eventId });

                    return {
                        success: true,
                        message: response.message || 'Event deleted successfully'
                    };
                }

                throw new Error(response.message || 'Failed to delete event');
            } catch (error) {
                console.error('CalendarAPI.deleteEvent error:', error);
                throw error;
            }
        },

        /**
         * Move/reschedule an event (drag and drop)
         * @param {string|number} eventId - Event ID
         * @param {string} start - New start date/time
         * @param {string} end - New end date/time
         * @returns {Promise<Object>} Updated event data
         */
        moveEvent: async function(eventId, start, end) {
            try {
                const response = await post('events.php', {
                    action: 'move',
                    id: eventId,
                    start: start,
                    end: end
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('calendar:event:moved', response.data);

                    return {
                        success: true,
                        event: response.data
                    };
                }

                throw new Error(response.message || 'Failed to move event');
            } catch (error) {
                console.error('CalendarAPI.moveEvent error:', error);
                throw error;
            }
        },

        /**
         * Resize an event (change duration)
         * @param {string|number} eventId - Event ID
         * @param {string} end - New end date/time
         * @returns {Promise<Object>} Updated event data
         */
        resizeEvent: async function(eventId, end) {
            try {
                const response = await post('events.php', {
                    action: 'resize',
                    id: eventId,
                    end: end
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('calendar:event:resized', response.data);

                    return {
                        success: true,
                        event: response.data
                    };
                }

                throw new Error(response.message || 'Failed to resize event');
            } catch (error) {
                console.error('CalendarAPI.resizeEvent error:', error);
                throw error;
            }
        },

        /**
         * Get calendar settings/preferences
         * @returns {Promise<Object>} Calendar settings
         */
        getSettings: async function() {
            try {
                const response = await get('events.php', {
                    action: 'settings'
                });

                return {
                    success: true,
                    settings: response.data || {}
                };
            } catch (error) {
                console.error('CalendarAPI.getSettings error:', error);
                throw error;
            }
        },

        /**
         * Update calendar settings
         * @param {Object} settings - New settings
         * @returns {Promise<Object>} Updated settings
         */
        updateSettings: async function(settings) {
            try {
                const response = await post('events.php', {
                    action: 'update_settings',
                    ...settings
                });

                return {
                    success: true,
                    settings: response.data || {},
                    message: response.message || 'Settings updated'
                };
            } catch (error) {
                console.error('CalendarAPI.updateSettings error:', error);
                throw error;
            }
        },

        /**
         * Share calendar with other users
         * @param {Array<string>} emails - User emails to share with
         * @param {string} permission - Permission level (view/edit)
         * @returns {Promise<Object>} Sharing result
         */
        shareCalendar: async function(emails, permission = 'view') {
            try {
                const response = await post('events.php', {
                    action: 'share',
                    emails: emails,
                    permission: permission
                });

                return {
                    success: true,
                    message: response.message || 'Calendar shared successfully'
                };
            } catch (error) {
                console.error('CalendarAPI.shareCalendar error:', error);
                throw error;
            }
        },

        /**
         * Export calendar events
         * @param {string} format - Export format (ical/csv)
         * @param {Object} options - Export options
         * @returns {Promise<Blob>} Exported data
         */
        exportEvents: async function(format = 'ical', options = {}) {
            try {
                const params = new URLSearchParams({
                    action: 'export',
                    format: format,
                    ...options
                });

                const url = window.APIConfig.buildApiUrl(`events.php?${params}`);
                const response = await fetch(url, {
                    ...window.APIConfig.getDefaultFetchOptions()
                });

                if (response.ok) {
                    return await response.blob();
                }

                throw new Error('Failed to export calendar');
            } catch (error) {
                console.error('CalendarAPI.exportEvents error:', error);
                throw error;
            }
        },

        /**
         * Import calendar events
         * @param {File} file - ICS/CSV file to import
         * @param {Object} options - Import options
         * @returns {Promise<Object>} Import result
         */
        importEvents: async function(file, options = {}) {
            try {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('action', 'import');
                Object.keys(options).forEach(key => {
                    formData.append(key, options[key]);
                });

                const response = await post('events.php', formData);

                if (response.success) {
                    // Trigger refresh
                    this._triggerEvent('calendar:events:imported', response.data);

                    return {
                        success: true,
                        imported: response.data.count || 0,
                        message: response.message || 'Events imported successfully'
                    };
                }

                throw new Error(response.message || 'Failed to import events');
            } catch (error) {
                console.error('CalendarAPI.importEvents error:', error);
                throw error;
            }
        },

        /**
         * Search calendar events
         * @param {string} query - Search query
         * @param {Object} filters - Additional filters
         * @returns {Promise<Object>} Search results
         */
        searchEvents: async function(query, filters = {}) {
            try {
                const response = await get('events.php', {
                    action: 'search',
                    q: query,
                    ...filters
                });

                return {
                    success: true,
                    events: response.data || [],
                    total: response.total || 0
                };
            } catch (error) {
                console.error('CalendarAPI.searchEvents error:', error);
                throw error;
            }
        },

        /**
         * Get recurring event instances
         * @param {string|number} eventId - Parent event ID
         * @param {Object} range - Date range
         * @returns {Promise<Object>} Event instances
         */
        getRecurringInstances: async function(eventId, range = {}) {
            try {
                const response = await get('events.php', {
                    action: 'instances',
                    id: eventId,
                    ...range
                });

                return {
                    success: true,
                    instances: response.data || []
                };
            } catch (error) {
                console.error('CalendarAPI.getRecurringInstances error:', error);
                throw error;
            }
        },

        /**
         * Subscribe to calendar updates via polling
         * @param {Function} callback - Callback for updates
         * @param {number} interval - Polling interval in ms (default 30s)
         * @returns {Function} Unsubscribe function
         */
        subscribeToUpdates: function(callback, interval = 30000) {
            let lastUpdate = new Date().toISOString();

            const pollForUpdates = async () => {
                try {
                    const response = await get('events.php', {
                        action: 'changes',
                        since: lastUpdate
                    });

                    if (response.success && response.data && response.data.length > 0) {
                        callback(response.data);
                        lastUpdate = new Date().toISOString();
                    }
                } catch (error) {
                    console.error('Calendar polling error:', error);
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
    window.CalendarAPI = CalendarAPI;

})(window);