import axios from 'axios';
import { useAuthStore } from '../stores/authStore';

// Configurazione base axios
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json'
  }
});

// Request interceptor per aggiungere token
api.interceptors.request.use(
  (config) => {
    const token = useAuthStore.getState().token;
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    // Aggiungi tenant header se presente
    const tenant = useAuthStore.getState().tenant;
    if (tenant) {
      config.headers['X-Tenant-ID'] = tenant.id;
    }

    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor per gestire errori e refresh token
api.interceptors.response.use(
  (response) => {
    return response;
  },
  async (error) => {
    const originalRequest = error.config;

    // Se 401 e non è già un retry, prova a refreshare il token
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;

      try {
        const newToken = await useAuthStore.getState().refreshAccessToken();
        if (newToken) {
          originalRequest.headers.Authorization = `Bearer ${newToken}`;
          return api(originalRequest);
        }
      } catch (refreshError) {
        // Refresh fallito, logout
        useAuthStore.getState().logout();
        window.location.href = '/login';
        return Promise.reject(refreshError);
      }
    }

    // Gestione errori generali
    if (error.response) {
      // Errore dal server
      const message = error.response.data?.message || 'Errore del server';
      console.error('API Error:', message);
    } else if (error.request) {
      // Nessuna risposta
      console.error('Nessuna risposta dal server');
    } else {
      // Errore nella configurazione
      console.error('Errore:', error.message);
    }

    return Promise.reject(error);
  }
);

// API endpoints
const apiEndpoints = {
  // Auth
  auth: {
    login: (credentials) => api.post('/auth/login', credentials),
    logout: () => api.post('/auth/logout'),
    refresh: (refreshToken) => api.post('/auth/refresh', { refreshToken }),
    me: () => api.get('/auth/me'),
    verify2FA: (code) => api.post('/auth/verify-2fa', { code }),
    resetPassword: (email) => api.post('/auth/reset-password', { email })
  },

  // Users
  users: {
    list: (params) => api.get('/users', { params }),
    get: (id) => api.get(`/users/${id}`),
    create: (data) => api.post('/users', data),
    update: (id, data) => api.put(`/users/${id}`, data),
    delete: (id) => api.delete(`/users/${id}`)
  },

  // Companies
  companies: {
    list: (params) => api.get('/companies', { params }),
    get: (id) => api.get(`/companies/${id}`),
    create: (data) => api.post('/companies', data),
    update: (id, data) => api.put(`/companies/${id}`, data),
    delete: (id) => api.delete(`/companies/${id}`)
  },

  // Tasks
  tasks: {
    list: (params) => api.get('/tasks', { params }),
    get: (id) => api.get(`/tasks/${id}`),
    create: (data) => api.post('/tasks', data),
    update: (id, data) => api.put(`/tasks/${id}`, data),
    delete: (id) => api.delete(`/tasks/${id}`),
    updateStatus: (id, status) => api.patch(`/tasks/${id}/status`, { status })
  },

  // Files
  files: {
    list: (params) => api.get('/files', { params }),
    upload: (formData) => api.post('/files/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    }),
    download: (id) => api.get(`/files/${id}/download`, { responseType: 'blob' }),
    delete: (id) => api.delete(`/files/${id}`),
    share: (id, data) => api.post(`/files/${id}/share`, data),
    getVersions: (id) => api.get(`/files/${id}/versions`)
  },

  // Calendar
  calendar: {
    events: (params) => api.get('/calendar/events', { params }),
    createEvent: (data) => api.post('/calendar/events', data),
    updateEvent: (id, data) => api.put(`/calendar/events/${id}`, data),
    deleteEvent: (id) => api.delete(`/calendar/events/${id}`)
  },

  // Chat
  chat: {
    conversations: () => api.get('/chat/conversations'),
    messages: (conversationId) => api.get(`/chat/conversations/${conversationId}/messages`),
    sendMessage: (conversationId, data) => api.post(`/chat/conversations/${conversationId}/messages`, data),
    markAsRead: (conversationId) => api.patch(`/chat/conversations/${conversationId}/read`)
  },

  // Notifications
  notifications: {
    list: () => api.get('/notifications'),
    markAsRead: (id) => api.patch(`/notifications/${id}/read`),
    markAllAsRead: () => api.patch('/notifications/read-all'),
    subscribe: (subscription) => api.post('/notifications/subscribe', subscription),
    unsubscribe: (endpoint) => api.post('/notifications/unsubscribe', { endpoint })
  },

  // Settings
  settings: {
    get: () => api.get('/settings'),
    update: (data) => api.put('/settings', data),
    updateTenant: (data) => api.put('/settings/tenant', data),
    updateSecurity: (data) => api.put('/settings/security', data)
  },

  // Dashboard
  dashboard: {
    stats: () => api.get('/dashboard/stats'),
    activity: () => api.get('/dashboard/activity'),
    charts: () => api.get('/dashboard/charts')
  }
};

export { api, apiEndpoints };