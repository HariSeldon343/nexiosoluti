import app from './app.js';
import pollingManager from './polling.js';

class ChatModule {
    constructor() {
        this.channels = [];
        this.currentChannelId = null;
        this.messages = [];
        document.addEventListener('collabora:auth-changed', () => this.init());
        document.addEventListener('collabora:tenant-changed', () => this.init());
        document.addEventListener('collabora:page-changed', (event) => {
            if (event.detail.page === 'chat') {
                this.renderMessages();
            }
        });
        this.bindEvents();
        pollingManager.subscribe((data) => this.handlePolling(data));
        this.init();
    }

    bindEvents() {
        const form = document.getElementById('chat-form');
        if (form) {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                this.sendMessage();
            });
        }
    }

    async init() {
        if (!app.user) {
            return;
        }
        await this.loadChannels();
        if (this.channels.length && !this.currentChannelId) {
            this.switchChannel(this.channels[0].id);
        }
    }

    async loadChannels() {
        try {
            const response = await app.authFetch('api/channels.php');
            const data = await response.json();
            this.channels = data.channels || [];
            const container = document.getElementById('chat-channels');
            if (container) {
                container.innerHTML = this.channels
                    .map((channel) => `<button class="chat-channel" data-id="${channel.id}">${channel.name || 'Senza nome'}</button>`)
                    .join('');
                container.querySelectorAll('.chat-channel').forEach((button) => {
                    button.addEventListener('click', () => {
                        const id = parseInt(button.dataset.id, 10);
                        this.switchChannel(id);
                    });
                });
            }
            pollingManager.setChannels(this.channels.map((channel) => channel.id));
        } catch (error) {
            console.error(error);
        }
    }

    async switchChannel(channelId) {
        this.currentChannelId = channelId;
        const channel = this.channels.find((item) => item.id === channelId);
        if (channel) {
            const title = document.getElementById('chat-channel-title');
            if (title) {
                title.textContent = channel.name || 'Chat';
            }
        }
        await this.loadMessages();
    }

    async loadMessages() {
        if (!this.currentChannelId) return;
        try {
            const response = await app.authFetch(`api/messages.php?channel_id=${this.currentChannelId}&last_id=0`);
            const data = await response.json();
            this.messages = data.messages || [];
            this.renderMessages();
        } catch (error) {
            console.error(error);
        }
    }

    renderMessages() {
        const container = document.getElementById('chat-messages');
        if (!container) return;
        const channelMessages = (this.messages || []).filter((message) => message.channel_id === this.currentChannelId || this.currentChannelId === null);
        container.innerHTML = channelMessages
            .map((message) => `
                <div class="chat-message">
                    <div class="chat-message-author">${message.user_name}</div>
                    <div class="chat-message-body">${message.content}</div>
                    <div class="chat-message-meta">${new Date(message.created_at).toLocaleString()}</div>
                </div>
            `)
            .join('');
        container.scrollTop = container.scrollHeight;
    }

    async sendMessage() {
        if (!this.currentChannelId) return;
        const textarea = document.getElementById('chat-input');
        if (!textarea || !textarea.value.trim()) return;
        const content = textarea.value.trim();
        textarea.value = '';
        try {
            const response = await app.authFetch('api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ channel_id: this.currentChannelId, content }),
            });
            const data = await response.json();
            if (data.success) {
                this.loadMessages();
            } else {
                app.showToast(data.message || 'Errore invio messaggio', 'error');
            }
        } catch (error) {
            console.error(error);
        }
    }

    handlePolling(data) {
        if (!data.messages) return;
        const newMessages = data.messages.filter((message) => this.currentChannelId === null || message.channel_id === this.currentChannelId);
        if (newMessages.length) {
            this.messages = (this.messages || []).concat(newMessages);
            this.renderMessages();
        }
    }
}

export const chatModule = new ChatModule();
export default chatModule;
