class PollingManager {
    constructor() {
        this.pollInterval = 5000;
        this.lastMessageId = 0;
        this.channels = [];
        if (window.app?.user) {
            this.start();
        }
    }

    setChannels(channelIds) {
        this.channels = channelIds;
    }

    start() {
        this.schedule();
    }

    schedule() {
        setTimeout(() => this.poll(), this.pollInterval);
    }

    async poll() {
        if (!this.channels.length) {
            this.schedule();
            return;
        }
        const params = new URLSearchParams({
            channels: this.channels.join(','),
            last_message_id: this.lastMessageId,
        });
        try {
            const response = await fetch(`api/chat-poll.php?${params.toString()}`);
            const data = await response.json();
            if (data.messages?.length) {
                this.lastMessageId = Math.max(this.lastMessageId, ...data.messages.map((m) => parseInt(m.id, 10)));
                window.chatModule?.appendMessages(data.messages);
            }
            window.chatModule?.updatePresence(data.presence || []);
            this.pollInterval = 5000;
        } catch (error) {
            console.error('Errore polling', error);
            this.pollInterval = Math.min(this.pollInterval * 2, 30000);
        }
        this.schedule();
    }
}

window.pollingManager = new PollingManager();
