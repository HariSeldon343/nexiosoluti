class ChatModule {
    constructor() {
        this.channelList = document.getElementById('chat-channels');
        this.history = document.getElementById('chat-history');
        this.form = document.getElementById('chat-form');
        this.input = document.getElementById('chat-input');
        this.currentChannel = null;
        this.channels = [];
        this.bindEvents();
        if (window.app?.user) {
            this.loadChannels();
        }
    }

    bindEvents() {
        this.form?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });
    }

    async loadChannels() {
        const response = await fetch('api/channels.php');
        const data = await response.json();
        this.channels = data.channels || [];
        this.renderChannels();
        if (this.channels.length) {
            this.selectChannel(this.channels[0].id);
        }
    }

    renderChannels() {
        if (!this.channelList) return;
        this.channelList.innerHTML = this.channels.map((channel) => `
            <div class="channel-item" data-id="${channel.id}"># ${channel.name}</div>
        `).join('');
        this.channelList.querySelectorAll('.channel-item').forEach((item) => {
            item.addEventListener('click', () => this.selectChannel(item.dataset.id));
        });
        window.pollingManager?.setChannels(this.channels.map((c) => c.id));
    }

    async selectChannel(channelId) {
        this.currentChannel = parseInt(channelId, 10);
        this.history.innerHTML = '';
        const response = await fetch(`api/messages.php?channel_id=${this.currentChannel}`);
        const data = await response.json();
        this.appendMessages(data.messages || []);
        window.pollingManager.lastMessageId = data.messages?.length ? Math.max(...data.messages.map((m) => parseInt(m.id, 10))) : 0;
    }

    appendMessages(messages) {
        messages.forEach((message) => {
            const item = document.createElement('div');
            item.className = 'chat-message';
            item.innerHTML = `<strong>${message.name || ''}</strong> <span>${new Date(message.created_at).toLocaleTimeString()}</span><p>${message.content}</p>`;
            this.history.appendChild(item);
        });
        this.history.scrollTop = this.history.scrollHeight;
    }

    updatePresence(presence) {
        if (!this.channelList) return;
        const onlineCount = presence.filter((p) => p.status === 'online').length;
        this.channelList.dataset.online = onlineCount;
    }

    async sendMessage() {
        const content = this.input?.value.trim();
        if (!content || !this.currentChannel) return;
        const payload = {
            csrf_token: window.app?.csrfToken || '',
            channel_id: this.currentChannel,
            content,
        };
        const response = await fetch('api/messages.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });
        const result = await response.json();
        if (result.success) {
            this.input.value = '';
            this.selectChannel(this.currentChannel);
        }
    }
}

window.chatModule = new ChatModule();
