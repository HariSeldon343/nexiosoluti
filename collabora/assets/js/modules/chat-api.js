/**
 * Chat API Module
 * Handles all real-time chat, messaging, and presence operations
 *
 * @module ChatAPI
 */

(function(window) {
    'use strict';

    // Ensure APIConfig is loaded
    if (!window.APIConfig) {
        console.error('ChatAPI: APIConfig module is required but not loaded');
        return;
    }

    const { get, post, put, delete: deleteRequest } = window.APIConfig;

    /**
     * Chat API endpoints and operations
     */
    const ChatAPI = {
        // Active polling intervals
        _pollingIntervals: new Map(),
        _presenceInterval: null,

        /**
         * Get chat rooms/channels for current user
         * @param {Object} options - Query options
         * @param {string} options.type - Room type (direct/group/channel)
         * @param {boolean} options.archived - Include archived rooms
         * @returns {Promise<Object>} Chat rooms data
         */
        getRooms: async function(options = {}) {
            try {
                const response = await get('messages.php', {
                    action: 'get_rooms',
                    ...options
                });

                if (response.success) {
                    return {
                        success: true,
                        rooms: response.data || [],
                        unread_total: response.unread_total || 0
                    };
                }

                throw new Error(response.message || 'Failed to fetch chat rooms');
            } catch (error) {
                console.error('ChatAPI.getRooms error:', error);
                throw error;
            }
        },

        /**
         * Get messages for a specific room
         * @param {string|number} roomId - Room/Channel ID
         * @param {Object} options - Query options
         * @param {number} options.limit - Number of messages to fetch
         * @param {string} options.before - Get messages before this ID
         * @param {string} options.after - Get messages after this ID
         * @returns {Promise<Object>} Messages data
         */
        getMessages: async function(roomId, options = {}) {
            try {
                const response = await get('messages.php', {
                    action: 'get_messages',
                    room_id: roomId,
                    limit: options.limit || 50,
                    ...options
                });

                if (response.success) {
                    return {
                        success: true,
                        messages: response.data || [],
                        has_more: response.has_more || false
                    };
                }

                throw new Error(response.message || 'Failed to fetch messages');
            } catch (error) {
                console.error('ChatAPI.getMessages error:', error);
                throw error;
            }
        },

        /**
         * Send a message
         * @param {string|number} roomId - Room/Channel ID
         * @param {string} message - Message content
         * @param {Object} options - Additional options
         * @param {Array} options.attachments - File attachments
         * @param {Object} options.reply_to - Message being replied to
         * @param {Array} options.mentions - User mentions
         * @returns {Promise<Object>} Sent message data
         */
        sendMessage: async function(roomId, message, options = {}) {
            try {
                const response = await post('messages.php', {
                    action: 'send',
                    room_id: roomId,
                    message: message,
                    ...options
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('chat:message:sent', response.data);

                    return {
                        success: true,
                        message: response.data
                    };
                }

                throw new Error(response.message || 'Failed to send message');
            } catch (error) {
                console.error('ChatAPI.sendMessage error:', error);
                throw error;
            }
        },

        /**
         * Edit a message
         * @param {string|number} messageId - Message ID
         * @param {string} newContent - New message content
         * @returns {Promise<Object>} Updated message data
         */
        editMessage: async function(messageId, newContent) {
            try {
                const response = await put('messages.php', {
                    action: 'edit',
                    message_id: messageId,
                    content: newContent
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('chat:message:edited', response.data);

                    return {
                        success: true,
                        message: response.data
                    };
                }

                throw new Error(response.message || 'Failed to edit message');
            } catch (error) {
                console.error('ChatAPI.editMessage error:', error);
                throw error;
            }
        },

        /**
         * Delete a message
         * @param {string|number} messageId - Message ID
         * @returns {Promise<Object>} Deletion result
         */
        deleteMessage: async function(messageId) {
            try {
                const response = await deleteRequest(`messages.php?message_id=${messageId}`);

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('chat:message:deleted', { id: messageId });

                    return {
                        success: true,
                        message: response.message || 'Message deleted'
                    };
                }

                throw new Error(response.message || 'Failed to delete message');
            } catch (error) {
                console.error('ChatAPI.deleteMessage error:', error);
                throw error;
            }
        },

        /**
         * Start real-time message polling for a room
         * @param {string|number} roomId - Room/Channel ID
         * @param {Function} onMessage - Callback for new messages
         * @param {number} interval - Polling interval in ms (default 3s)
         * @returns {Function} Stop polling function
         */
        startPolling: function(roomId, onMessage, interval = 3000) {
            // Stop existing polling for this room if any
            this.stopPolling(roomId);

            let lastMessageId = null;

            const poll = async () => {
                try {
                    const response = await get('chat-poll.php', {
                        room_id: roomId,
                        last_id: lastMessageId
                    });

                    if (response.success && response.data && response.data.messages) {
                        const messages = response.data.messages;
                        if (messages.length > 0) {
                            onMessage(messages);
                            // Update last message ID
                            lastMessageId = messages[messages.length - 1].id;
                        }
                    }
                } catch (error) {
                    console.error('Chat polling error:', error);
                }
            };

            // Start polling
            const intervalId = setInterval(poll, interval);
            this._pollingIntervals.set(roomId, intervalId);

            // Initial poll
            poll();

            // Return stop function
            return () => this.stopPolling(roomId);
        },

        /**
         * Stop polling for a specific room
         * @param {string|number} roomId - Room/Channel ID
         */
        stopPolling: function(roomId) {
            if (this._pollingIntervals.has(roomId)) {
                clearInterval(this._pollingIntervals.get(roomId));
                this._pollingIntervals.delete(roomId);
            }
        },

        /**
         * Stop all active polling
         */
        stopAllPolling: function() {
            this._pollingIntervals.forEach(intervalId => clearInterval(intervalId));
            this._pollingIntervals.clear();
        },

        /**
         * Mark messages as read
         * @param {string|number} roomId - Room/Channel ID
         * @param {string|number} messageId - Last read message ID
         * @returns {Promise<Object>} Mark read result
         */
        markAsRead: async function(roomId, messageId) {
            try {
                const response = await post('messages.php', {
                    action: 'mark_read',
                    room_id: roomId,
                    message_id: messageId
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('chat:messages:read', {
                        room_id: roomId,
                        message_id: messageId
                    });

                    return {
                        success: true
                    };
                }

                throw new Error(response.message || 'Failed to mark as read');
            } catch (error) {
                console.error('ChatAPI.markAsRead error:', error);
                throw error;
            }
        },

        /**
         * Send typing indicator
         * @param {string|number} roomId - Room/Channel ID
         * @param {boolean} isTyping - Typing status
         * @returns {Promise<Object>} Typing indicator result
         */
        sendTypingIndicator: async function(roomId, isTyping = true) {
            try {
                const response = await post('messages.php', {
                    action: 'typing',
                    room_id: roomId,
                    typing: isTyping
                });

                return {
                    success: true
                };
            } catch (error) {
                console.error('ChatAPI.sendTypingIndicator error:', error);
                // Don't throw for typing indicators
                return { success: false };
            }
        },

        /**
         * Create a new chat room
         * @param {Object} roomData - Room data
         * @param {string} roomData.name - Room name
         * @param {string} roomData.type - Room type (direct/group/channel)
         * @param {Array} roomData.members - Member user IDs
         * @param {string} roomData.description - Room description
         * @returns {Promise<Object>} Created room data
         */
        createRoom: async function(roomData) {
            try {
                const response = await post('messages.php', {
                    action: 'create_room',
                    ...roomData
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('chat:room:created', response.data);

                    return {
                        success: true,
                        room: response.data,
                        message: response.message || 'Room created successfully'
                    };
                }

                throw new Error(response.message || 'Failed to create room');
            } catch (error) {
                console.error('ChatAPI.createRoom error:', error);
                throw error;
            }
        },

        /**
         * Update room settings
         * @param {string|number} roomId - Room ID
         * @param {Object} updates - Room updates
         * @returns {Promise<Object>} Updated room data
         */
        updateRoom: async function(roomId, updates) {
            try {
                const response = await put('messages.php', {
                    action: 'update_room',
                    room_id: roomId,
                    ...updates
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('chat:room:updated', response.data);

                    return {
                        success: true,
                        room: response.data
                    };
                }

                throw new Error(response.message || 'Failed to update room');
            } catch (error) {
                console.error('ChatAPI.updateRoom error:', error);
                throw error;
            }
        },

        /**
         * Leave/archive a chat room
         * @param {string|number} roomId - Room ID
         * @returns {Promise<Object>} Leave result
         */
        leaveRoom: async function(roomId) {
            try {
                const response = await post('messages.php', {
                    action: 'leave_room',
                    room_id: roomId
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('chat:room:left', { room_id: roomId });

                    return {
                        success: true,
                        message: response.message || 'Left room successfully'
                    };
                }

                throw new Error(response.message || 'Failed to leave room');
            } catch (error) {
                console.error('ChatAPI.leaveRoom error:', error);
                throw error;
            }
        },

        /**
         * Get user presence status
         * @param {Array<string>} userIds - User IDs to check
         * @returns {Promise<Object>} Presence data
         */
        getPresence: async function(userIds = []) {
            try {
                const response = await get('presence.php', {
                    action: 'get',
                    users: userIds
                });

                if (response.success) {
                    return {
                        success: true,
                        presence: response.data || {}
                    };
                }

                throw new Error(response.message || 'Failed to get presence');
            } catch (error) {
                console.error('ChatAPI.getPresence error:', error);
                throw error;
            }
        },

        /**
         * Update own presence status
         * @param {string} status - Status (online/away/busy/offline)
         * @param {string} message - Status message
         * @returns {Promise<Object>} Presence update result
         */
        updatePresence: async function(status, message = '') {
            try {
                const response = await post('presence.php', {
                    action: 'update',
                    status: status,
                    message: message
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('chat:presence:updated', response.data);

                    return {
                        success: true
                    };
                }

                throw new Error(response.message || 'Failed to update presence');
            } catch (error) {
                console.error('ChatAPI.updatePresence error:', error);
                throw error;
            }
        },

        /**
         * Start presence heartbeat
         * @param {number} interval - Heartbeat interval in ms (default 30s)
         * @returns {Function} Stop heartbeat function
         */
        startPresenceHeartbeat: function(interval = 30000) {
            // Stop existing heartbeat if any
            this.stopPresenceHeartbeat();

            const heartbeat = async () => {
                try {
                    await this.updatePresence('online');
                } catch (error) {
                    console.error('Presence heartbeat error:', error);
                }
            };

            // Initial heartbeat
            heartbeat();

            // Start interval
            this._presenceInterval = setInterval(heartbeat, interval);

            // Return stop function
            return () => this.stopPresenceHeartbeat();
        },

        /**
         * Stop presence heartbeat
         */
        stopPresenceHeartbeat: function() {
            if (this._presenceInterval) {
                clearInterval(this._presenceInterval);
                this._presenceInterval = null;
            }
        },

        /**
         * Search messages
         * @param {string} query - Search query
         * @param {Object} options - Search options
         * @returns {Promise<Object>} Search results
         */
        searchMessages: async function(query, options = {}) {
            try {
                const response = await get('messages.php', {
                    action: 'search',
                    q: query,
                    ...options
                });

                return {
                    success: true,
                    messages: response.data || [],
                    total: response.total || 0
                };
            } catch (error) {
                console.error('ChatAPI.searchMessages error:', error);
                throw error;
            }
        },

        /**
         * Upload file attachment
         * @param {string|number} roomId - Room ID
         * @param {File} file - File to upload
         * @param {Function} onProgress - Progress callback
         * @returns {Promise<Object>} Upload result
         */
        uploadAttachment: async function(roomId, file, onProgress = null) {
            try {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('room_id', roomId);
                formData.append('action', 'upload_attachment');

                const response = await window.APIConfig.uploadWithProgress(
                    'messages.php',
                    formData,
                    onProgress
                );

                if (response.success) {
                    return {
                        success: true,
                        attachment: response.data
                    };
                }

                throw new Error(response.message || 'Failed to upload attachment');
            } catch (error) {
                console.error('ChatAPI.uploadAttachment error:', error);
                throw error;
            }
        },

        /**
         * Get unread message counts
         * @returns {Promise<Object>} Unread counts by room
         */
        getUnreadCounts: async function() {
            try {
                const response = await get('messages.php', {
                    action: 'unread_counts'
                });

                return {
                    success: true,
                    counts: response.data || {},
                    total: response.total || 0
                };
            } catch (error) {
                console.error('ChatAPI.getUnreadCounts error:', error);
                throw error;
            }
        },

        /**
         * Add reaction to message
         * @param {string|number} messageId - Message ID
         * @param {string} emoji - Reaction emoji
         * @returns {Promise<Object>} Reaction result
         */
        addReaction: async function(messageId, emoji) {
            try {
                const response = await post('messages.php', {
                    action: 'add_reaction',
                    message_id: messageId,
                    emoji: emoji
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('chat:reaction:added', response.data);

                    return {
                        success: true
                    };
                }

                throw new Error(response.message || 'Failed to add reaction');
            } catch (error) {
                console.error('ChatAPI.addReaction error:', error);
                throw error;
            }
        },

        /**
         * Remove reaction from message
         * @param {string|number} messageId - Message ID
         * @param {string} emoji - Reaction emoji
         * @returns {Promise<Object>} Reaction removal result
         */
        removeReaction: async function(messageId, emoji) {
            try {
                const response = await post('messages.php', {
                    action: 'remove_reaction',
                    message_id: messageId,
                    emoji: emoji
                });

                if (response.success) {
                    // Trigger event for UI update
                    this._triggerEvent('chat:reaction:removed', response.data);

                    return {
                        success: true
                    };
                }

                throw new Error(response.message || 'Failed to remove reaction');
            } catch (error) {
                console.error('ChatAPI.removeReaction error:', error);
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
        },

        /**
         * Cleanup all active connections
         */
        cleanup: function() {
            this.stopAllPolling();
            this.stopPresenceHeartbeat();
        }
    };

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        ChatAPI.cleanup();
    });

    // Export to global scope
    window.ChatAPI = ChatAPI;

})(window);