import app from './app.js';

class NotificationsModule {
    constructor() {
        this.lastNotified = new Set();
        document.addEventListener('collabora:auth-changed', () => this.reset());
        document.addEventListener('collabora:tenant-changed', () => this.reset());
        this.start();
    }

    reset() {
        this.lastNotified.clear();
    }

    start() {
        setInterval(() => {
            if (app.user) {
                this.fetchNotifications();
            }
        }, 60000);
    }

    async fetchNotifications() {
        try {
            const response = await app.authFetch('api/notifications.php?unread=true');
            const data = await response.json();
            (data.notifications || []).forEach((notification) => {
                if (!this.lastNotified.has(notification.id)) {
                    app.showToast(notification.title, 'info');
                    this.lastNotified.add(notification.id);
                }
            });
        } catch (error) {
            console.error(error);
        }
    }
}

export const notificationsModule = new NotificationsModule();
export default notificationsModule;
