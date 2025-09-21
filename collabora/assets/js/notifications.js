class NotificationsModule {
    constructor() {
        if (window.app?.user) {
            this.load();
        }
    }

    async load() {
        const response = await fetch('api/notifications.php?unread=true');
        const data = await response.json();
        this.notifications = data.notifications || [];
    }
}

window.notificationsModule = new NotificationsModule();
