import { create } from 'zustand';
import { persist } from 'zustand/middleware';

/**
 * Store per la gestione delle notifiche
 */
const useNotificationStore = create(
  persist(
    (set, get) => ({
      notifications: [],
      unreadCount: 0,
      pushSubscription: null,
      permissionGranted: false,

      // Aggiungi notifica
      addNotification: (notification) => {
        const newNotification = {
          id: Date.now().toString(),
          ...notification,
          read: false,
          time: new Date().toLocaleString('it-IT')
        };

        set(state => ({
          notifications: [newNotification, ...state.notifications],
          unreadCount: state.unreadCount + 1
        }));

        // Mostra notifica browser se permesso
        if (get().permissionGranted && 'Notification' in window) {
          new Notification(notification.title, {
            body: notification.message,
            icon: '/icons/icon-192x192.png',
            badge: '/icons/icon-72x72.png',
            tag: newNotification.id,
            requireInteraction: notification.requireInteraction || false
          });
        }
      },

      // Segna come letta
      markAsRead: (notificationId) => {
        set(state => ({
          notifications: state.notifications.map(n =>
            n.id === notificationId ? { ...n, read: true } : n
          ),
          unreadCount: Math.max(0, state.unreadCount - 1)
        }));
      },

      // Segna tutte come lette
      markAllAsRead: () => {
        set(state => ({
          notifications: state.notifications.map(n => ({ ...n, read: true })),
          unreadCount: 0
        }));
      },

      // Elimina notifica
      removeNotification: (notificationId) => {
        set(state => {
          const notification = state.notifications.find(n => n.id === notificationId);
          return {
            notifications: state.notifications.filter(n => n.id !== notificationId),
            unreadCount: notification && !notification.read
              ? Math.max(0, state.unreadCount - 1)
              : state.unreadCount
          };
        });
      },

      // Elimina tutte le notifiche
      clearNotifications: () => {
        set({
          notifications: [],
          unreadCount: 0
        });
      },

      // Richiedi permesso notifiche
      requestNotificationPermission: async () => {
        if (!('Notification' in window)) {
          console.log('Questo browser non supporta le notifiche desktop');
          return false;
        }

        if (Notification.permission === 'granted') {
          set({ permissionGranted: true });
          return true;
        }

        if (Notification.permission !== 'denied') {
          const permission = await Notification.requestPermission();
          const granted = permission === 'granted';
          set({ permissionGranted: granted });
          return granted;
        }

        return false;
      },

      // Sottoscrivi push notifications
      subscribeToPush: async () => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
          console.log('Push notifications non supportate');
          return null;
        }

        try {
          const registration = await navigator.serviceWorker.ready;
          const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: import.meta.env.VITE_VAPID_PUBLIC_KEY
          });

          set({ pushSubscription: subscription });

          // Invia subscription al server
          await fetch('/api/notifications/subscribe', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${localStorage.getItem('token')}`
            },
            body: JSON.stringify(subscription)
          });

          return subscription;
        } catch (error) {
          console.error('Errore nella sottoscrizione push:', error);
          return null;
        }
      },

      // Annulla sottoscrizione push
      unsubscribeFromPush: async () => {
        const subscription = get().pushSubscription;
        if (!subscription) return;

        try {
          await subscription.unsubscribe();
          set({ pushSubscription: null });

          // Notifica il server
          await fetch('/api/notifications/unsubscribe', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${localStorage.getItem('token')}`
            },
            body: JSON.stringify({ endpoint: subscription.endpoint })
          });
        } catch (error) {
          console.error('Errore nell\'annullamento sottoscrizione:', error);
        }
      }
    }),
    {
      name: 'notification-storage',
      partialize: (state) => ({
        notifications: state.notifications,
        unreadCount: state.unreadCount,
        permissionGranted: state.permissionGranted
      })
    }
  )
);

export { useNotificationStore };