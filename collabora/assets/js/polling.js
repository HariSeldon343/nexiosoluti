import app from './app.js';

class PollingManager {
    constructor() {
        this.pollInterval = 2000;
        this.lastMessageId = 0;
        this.isPolling = false;
        this.channelIds = [];
        this.callbacks = [];
        document.addEventListener('collabora:auth-changed', () => this.reset());
        document.addEventListener('collabora:tenant-changed', () => this.reset());
        if (app.user) {
            this.start();
        }
    }

    reset() {
        this.lastMessageId = 0;
        this.pollInterval = 2000;
        this.channelIds = [];
        if (app.user) {
            this.start();
        }
    }

    setChannels(channelIds) {
        this.channelIds = channelIds;
        if (!this.isPolling) {
            this.start();
        }
    }

    subscribe(callback) {
        this.callbacks.push(callback);
    }

    start() {
        setTimeout(() => this.poll(), this.pollInterval);
    }

    async poll() {
        if (!app.user || this.isPolling) {
            return;
        }
        this.isPolling = true;
        const params = new URLSearchParams();
        if (this.channelIds.length) {
            params.set('channels', this.channelIds.join(','));
        }
        params.set('last_message_id', this.lastMessageId);
        try {
            const response = await app.authFetch(`api/chat-poll.php?${params.toString()}`);
            const data = await response.json();
            if (data.messages && data.messages.length) {
                const maxId = Math.max(...data.messages.map((message) => message.id));
                this.lastMessageId = Math.max(this.lastMessageId, maxId);
                this.callbacks.forEach((callback) => callback(data));
                this.pollInterval = 2000;
            } else {
                this.pollInterval = Math.min(this.pollInterval + 1000, 30000);
            }
        } catch (error) {
            console.warn('Errore polling', error);
            this.pollInterval = Math.min(this.pollInterval * 2, 30000);
        }
        this.isPolling = false;
        setTimeout(() => this.poll(), this.pollInterval);
    }
}

export const pollingManager = new PollingManager();
export default pollingManager;
