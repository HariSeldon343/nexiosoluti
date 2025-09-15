import axios from 'axios';
import toast from 'react-hot-toast';

// Create axios instance with default config
const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true, // Important for CORS with cookies
});

// Request interceptor to add token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor to handle errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response) {
      // Server responded with error status
      if (error.response.status === 401) {
        // Unauthorized - token expired or invalid
        localStorage.removeItem('token');
        window.location.href = '/login';
        toast.error('Sessione scaduta, effettua nuovamente il login');
      } else if (error.response.status === 403) {
        toast.error('Non hai i permessi per questa azione');
      } else if (error.response.status === 404) {
        toast.error('Risorsa non trovata');
      } else if (error.response.status === 422) {
        // Validation errors
        const errors = error.response.data.errors;
        if (errors) {
          Object.values(errors).flat().forEach(msg => toast.error(msg));
        }
      } else if (error.response.status >= 500) {
        toast.error('Errore del server, riprova più tardi');
      }
    } else if (error.request) {
      // Request made but no response
      toast.error('Impossibile connettersi al server');
    } else {
      // Something else happened
      toast.error('Si è verificato un errore');
    }
    return Promise.reject(error);
  }
);

// API endpoints
export const authAPI = {
  login: (credentials) => api.post('/login', credentials),
  // Registration removed - only admins can create users via usersAPI.create()
  logout: () => api.post('/logout'),
  getCurrentUser: () => api.get('/user'),
  updateProfile: (data) => api.put('/user/profile', data),
  changePassword: (data) => api.put('/user/password', data),
};

export const companiesAPI = {
  getAll: (params) => api.get('/companies', { params }),
  getOne: (id) => api.get(`/companies/${id}`),
  create: (data) => api.post('/companies', data),
  update: (id, data) => api.put(`/companies/${id}`, data),
  delete: (id) => api.delete(`/companies/${id}`),
};

export const usersAPI = {
  getAll: (params) => api.get('/users', { params }),
  getOne: (id) => api.get(`/users/${id}`),
  create: (data) => api.post('/users', data),
  update: (id, data) => api.put(`/users/${id}`, data),
  delete: (id) => api.delete(`/users/${id}`),
  getRoles: () => api.get('/roles'),
  assignRole: (userId, roleId) => api.post(`/users/${userId}/roles`, { role_id: roleId }),
};

export const tasksAPI = {
  getAll: (params) => api.get('/tasks', { params }),
  getOne: (id) => api.get(`/tasks/${id}`),
  create: (data) => api.post('/tasks', data),
  update: (id, data) => api.put(`/tasks/${id}`, data),
  delete: (id) => api.delete(`/tasks/${id}`),
  updateStatus: (id, status) => api.patch(`/tasks/${id}/status`, { status }),
  addComment: (id, comment) => api.post(`/tasks/${id}/comments`, { comment }),
};

export const eventsAPI = {
  getAll: (params) => api.get('/events', { params }),
  getOne: (id) => api.get(`/events/${id}`),
  create: (data) => api.post('/events', data),
  update: (id, data) => api.put(`/events/${id}`, data),
  delete: (id) => api.delete(`/events/${id}`),
};

export const filesAPI = {
  getAll: (params) => api.get('/files', { params }),
  getOne: (id) => api.get(`/files/${id}`),
  upload: (formData) => api.post('/files', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  update: (id, data) => api.put(`/files/${id}`, data),
  delete: (id) => api.delete(`/files/${id}`),
  download: (id) => api.get(`/files/${id}/download`, { responseType: 'blob' }),
  createFolder: (data) => api.post('/folders', data),
};

export const chatAPI = {
  getConversations: () => api.get('/conversations'),
  getMessages: (conversationId) => api.get(`/conversations/${conversationId}/messages`),
  sendMessage: (conversationId, message) => api.post(`/conversations/${conversationId}/messages`, { message }),
  createConversation: (participants) => api.post('/conversations', { participants }),
  markAsRead: (conversationId) => api.patch(`/conversations/${conversationId}/read`),
};

export const dashboardAPI = {
  getStats: () => api.get('/dashboard/stats'),
  getActivities: () => api.get('/dashboard/activities'),
  getChartData: (type) => api.get(`/dashboard/charts/${type}`),
};

export const settingsAPI = {
  getGeneral: () => api.get('/settings/general'),
  updateGeneral: (data) => api.put('/settings/general', data),
  getNotifications: () => api.get('/settings/notifications'),
  updateNotifications: (data) => api.put('/settings/notifications', data),
  getSecurity: () => api.get('/settings/security'),
  updateSecurity: (data) => api.put('/settings/security', data),
};

export default api;
export { api };